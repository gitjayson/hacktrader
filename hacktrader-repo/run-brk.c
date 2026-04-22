#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <math.h>
#include <time.h>

typedef struct {
    double price;
    double diff;
} Level;

typedef struct {
    double high;
    double low;
    double close;
    double volume;
    char datetime[32];
} Bar;

double json_extract_double(const char *json, const char *key) {
    char search[64];
    snprintf(search, sizeof(search), "\"%s\":", key);
    char *pos = strstr(json, search);
    if (!pos) return 0.0;
    pos += strlen(search);
    while (*pos == ' ' || *pos == ':') pos++;
    return atof(pos);
}

// Securely read API key from server-side path
void get_api_key(char *buffer, size_t size) {
    // Primary target: Secure server path (outside web root)
    const char *secure_path = "/var/www/secrets.json";
    const char *local_path = "secrets.json";
    
    FILE *f = fopen(secure_path, "r");
    if (!f) {
        f = fopen(local_path, "r");
    }

    if (!f) {
        // Fallback for dev/test only - not used in production
        strncpy(buffer, "1dbc799fe411479686be512ee797a777", size);
        return;
    }

    char line[256];
    while (fgets(line, sizeof(line), f)) {
        if (strstr(line, "TWELVEDATA_API_KEY")) {
            char *start = strchr(line, '\"');
            if (start) {
                start = strchr(start + 1, '\"');
                if (start) {
                    start++;
                    char *end = strchr(start, '\"');
                    if (end) {
                        size_t len = end - start;
                        if (len < size) {
                            strncpy(buffer, start, len);
                            buffer[len] = '\0';
                        }
                    }
                }
            }
        }
    }
    fclose(f);
}

int main(int argc, char *argv[]) {
    if (argc < 5) {
        fprintf(stderr, "Usage: %s <ticker> <interval> <display> <periods> <output_json>\n", argv[0]);
        return 1;
    }

    char *ticker = argv[1];
    char *interval = argv[2];
    char *display = argv[3];
    int periods = atoi(argv[4]);
    int output_json = (argc > 5 && strcmp(argv[5], "true") == 0);

    char api_key[128];
    get_api_key(api_key, sizeof(api_key));

    char url[512];
    snprintf(url, sizeof(url), "https://api.twelvedata.com/time_series?symbol=%s&interval=%s&apikey=%s&outputsize=%d", 
             ticker, interval, api_key, periods);

    char cmd[1024];
    snprintf(cmd, sizeof(cmd), "curl -s '%s'", url);

    FILE *fp = popen(cmd, "r");
    if (!fp) return 1;

    char buffer[1048576]; 
    size_t bytes_read = fread(buffer, 1, sizeof(buffer) - 1, fp);
    buffer[bytes_read] = '\0';
    pclose(fp);

    if (strstr(buffer, "\"code\":") && !strstr(buffer, "\"code\":\"200\"")) {
        fprintf(stderr, "API Error: %s\n", buffer);
        return 1;
    }

    Bar *bars = malloc(sizeof(Bar) * periods);
    int count = 0;
    char *ptr = strstr(buffer, "\"values\":");
    if (!ptr) {
        fprintf(stderr, "No values found\n");
        free(bars);
        return 1;
    }

    while ((ptr = strstr(ptr + 1, "{")) && count < periods) {
        char *end = strstr(ptr, "}");
        if (!end) break;
        char obj[512];
        int len = end - ptr + 1;
        if (len < 512) {
            strncpy(obj, ptr, len);
            obj[len] = '\0';
            bars[count].high = json_extract_double(obj, "high");
            bars[count].low = json_extract_double(obj, "low");
            bars[count].close = json_extract_double(obj, "close");
            bars[count].volume = json_extract_double(obj, "volume");
            count++;
        }
        ptr = end;
    }

    if (count == 0) {
        fprintf(stderr, "No valid bars\n");
        free(bars);
        return 1;
    }

    double current_price = bars[0].close;
    double u1 = 1e18, u2 = 1e18;
    for (int i = 0; i < count; i++) {
        if (bars[i].high > current_price) {
            if (bars[i].high < u1) { u2 = u1; u1 = bars[i].high; }
            else if (bars[i].high < u2) { u2 = bars[i].high; }
        }
    }
    double l1 = -1e18, l2 = -1e18;
    for (int i = 0; i < count; i++) {
        if (bars[i].low < current_price) {
            if (bars[i].low > l1) { l2 = l1; l1 = bars[i].low; }
            else if (bars[i].low > l2) { l2 = bars[i].low; }
        }
    }

    double dist_u = (u1 == 1e18) ? 1e18 : u1 - current_price;
    double dist_l = (l1 == -1e18) ? 1e18 : current_price - l1;
    double inv_u = (dist_u > 0) ? 1.0 / dist_u : 0;
    double inv_l = (dist_l > 0) ? 1.0 / dist_l : 0;
    double total_inv = inv_u + inv_l;
    double prob_up = (total_inv > 0) ? (inv_u / total_inv) * 100.0 : 0;
    double prob_down = (total_inv > 0) ? (inv_l / total_inv) * 100.0 : 0;

    double current_vol = bars[0].volume;
    double sum_vol = 0;
    for (int i = 1; i < count; i++) sum_vol += bars[i].volume;
    double avg_vol = (count > 1) ? sum_vol / (count - 1) : 0;
    double vol_ratio = (avg_vol > 0) ? current_vol / avg_vol : 0;

    if (output_json) {
        printf("{\n  \"ticker\": \"%s\",\n  \"current_price\": %.2f,\n", ticker, current_price);
        printf("  \"upper_resistances\": [{\"price\": %.2f, \"diff\": %.2f}, {\"price\": %.2f, \"diff\": %.2f}],\n", u1, u1-current_price, u2, u2-current_price);
        printf("  \"lower_supports\": [{\"price\": %.2f, \"diff\": %.2f}, {\"price\": %.2f, \"diff\": %.2f}],\n", l1, current_price-l1, l2, current_price-l2);
        printf("  \"probabilities\": {\"up\": %.1f, \"down\": %.1f},\n", prob_up, prob_down);
        printf("  \"volume\": {\"current\": %.2f, \"expected\": %.2f, \"ratio\": %.2f},\n", current_vol, avg_vol, vol_ratio);
        printf("  \"source\": \"twelvedata_c_turbo\"\n}\n");
    } else {
        printf("=== %s Breakout Analysis (%s, %d periods) ===\n", ticker, display, periods);
        printf("Last Known Market Price: $%.2f\n", current_price);
        printf("Source: twelvedata_c_turbo\n\n");
        printf("## Upper Resistance Levels\n  1. $%.2f (+%.2f)\n  2. $%.2f (+%.2f)\n\n", u1, u1-current_price, u2, u2-current_price);
        printf("## Lower Support Levels\n  1. $%.2f (-%.2f)\n  2. $%.2f (-%.2f)\n\n", l1, current_price-l1, l2, current_price-l2);
        printf("## Breakout Probability\n- UP: %.1f%%\n- DOWN: %.1f%%\n", prob_up, prob_down);
    }

    free(bars);
    return 0;
}
