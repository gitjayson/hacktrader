/* LEGACY / EXPERIMENTAL: The production HackTrader path runs through run-brk.py via run-brk.sh.
 * Do not route live dashboard traffic to this C runner until feature-parity tests exist. */
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
    long long timestamp;
    char datetime[32];
} Bar;

typedef struct {
    char td_key[128];
    char massive_key[128];
} Secrets;

double json_extract_double(const char *json, const char *key) {
    char search[64];
    snprintf(search, sizeof(search), "\"%s\":", key);
    char *pos = strstr(json, search);
    if (!pos) return 0.0;
    pos += strlen(search);
    while (*pos == ' ' || *pos == ':') pos++;
    return atof(pos);
}

// Securely read API keys from server-side path
void load_secrets(Secrets *s) {
    memset(s, 0, sizeof(Secrets));
    const char *secure_path = "/var/www/secrets.json";
    const char *local_path = "secrets.json";
    
    FILE *f = fopen(secure_path, "r");
    if (!f) f = fopen(local_path, "r");

    if (!f) {
        strncpy(s->td_key, "1dbc799fe411479686be512ee797a777", sizeof(s->td_key));
        return;
    }

    char line[256];
    while (fgets(line, sizeof(line), f)) {
        char *key_name = NULL;
        if (strstr(line, "\"TWELVEDATA_API_KEY\"")) key_name = s->td_key;
        else if (strstr(line, "\"MASSIVE_API_KEY\"")) key_name = s->massive_key;
        
        if (key_name) {
            char *val_start = strchr(line, ':');
            if (val_start) {
                val_start = strchr(val_start + 1, '\"');
                if (val_start) {
                    val_start++;
                    char *val_end = strchr(val_start, '\"');
                    if (val_end) {
                        size_t len = val_end - val_start;
                        if (len < 128) {
                            strncpy(key_name, val_start, len);
                            key_name[len] = '\0';
                        }
                    }
                }
            }
        }
    }
    fclose(f);
}

// Helper for Massive API interval mapping
void map_massive_interval(const char *interval, int *mul, char *span) {
    if (strcmp(interval, "1min") == 0) { *mul = 1; strcpy(span, "minute"); }
    else if (strcmp(interval, "5min") == 0) { *mul = 5; strcpy(span, "minute"); }
    else if (strcmp(interval, "1h") == 0) { *mul = 1; strcpy(span, "hour"); }
    else { *mul = 1; strcpy(span, "day"); }
}

int fetch_massive(const char *ticker, const char *interval, int periods, const char *api_key, Bar *bars) {
    int mul;
    char span[16];
    map_massive_interval(interval, &mul, span);

    char from_date[11], to_date[11];
    FILE *fp_from = popen("date -u -d '30 days ago' +%Y-%m-%d", "r");
    FILE *fp_to = popen("date -u +%Y-%m-%d", "r");
    if (!fp_from || !fp_to) return 0;
    fgets(from_date, 11, fp_from);
    fgets(to_date, 11, fp_to);
    pclose(fp_from);
    pclose(fp_to);
    
    // Remove newlines
    from_date[strcspn(from_date, "\r\n")] = 0;
    to_date[strcspn(to_date, "\r\n")] = 0;

    char url[512];
    snprintf(url, sizeof(url), "https://api.massive.com/v2/aggs/ticker/%s/range/%d/%s/%s/%s?adjusted=true&sort=desc&limit=50000&apiKey=%s",
             ticker, mul, span, from_date, to_date, api_key);

    char cmd[1024];
    snprintf(cmd, sizeof(cmd), "curl -s '%s'", url);

    FILE *fp = popen(cmd, "r");
    if (!fp) return 0;
    char buffer[1048576]; 
    size_t bytes_read = fread(buffer, 1, sizeof(buffer) - 1, fp);
    buffer[bytes_read] = '\0';
    pclose(fp);

    if (strstr(buffer, "\"status\":\"ERROR\"") || !strstr(buffer, "\"results\":")) {
        return 0;
    }

    int count = 0;
    char *ptr = strstr(buffer, "\"results\":");
    if (!ptr) return 0;
    ptr++;

    while ((ptr = strstr(ptr, "{")) && count < periods) {
        char *end = strstr(ptr, "}");
        if (!end) break;
        char obj[512];
        int len = end - ptr + 1;
        if (len < 512) {
            strncpy(obj, ptr, len);
            obj[len] = '\0';
            // Massive fields: h, l, c, v, t
            bars[count].high = json_extract_double(obj, "h");
            bars[count].low = json_extract_double(obj, "l");
            bars[count].close = json_extract_double(obj, "c");
            bars[count].volume = json_extract_double(obj, "v");
            // Extract timestamp t (it is a long)
            char *t_ptr = strstr(obj, "\"t\":");
            if (t_ptr) {
                t_ptr += 3;
                while (*t_ptr == ' ' || *t_ptr == ':') t_ptr++;
                bars[count].timestamp = atoll(t_ptr);
            }
            count++;
        }
        ptr = end;
    }
    return count;
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

    Secrets s;
    load_secrets(&s);

    Bar *bars = malloc(sizeof(Bar) * periods);
    int count = 0;
    char *source = "unknown";

    // 1. Try Twelve Data
    if (strlen(s.td_key) > 0) {
        char url[512];
        snprintf(url, sizeof(url), "https://api.twelvedata.com/time_series?symbol=%s&interval=%s&apikey=%s&outputsize=%d", 
                 ticker, interval, s.td_key, periods);
        char cmd[1024];
        snprintf(cmd, sizeof(cmd), "curl -s '%s'", url);
        FILE *fp = popen(cmd, "r");
        if (fp) {
            char buffer[1048576]; 
            size_t bytes_read = fread(buffer, 1, sizeof(buffer) - 1, fp);
            buffer[bytes_read] = '\0';
            pclose(fp);
            
            if (!strstr(buffer, "\"code\":") || strstr(buffer, "\"code\":\"200\"")) {
                char *ptr = strstr(buffer, "\"values\":");
                if (ptr) {
                    ptr++;
                    while ((ptr = strstr(ptr, "{")) && count < periods) {
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
                    if (count > 0) {
                        source = "twelvedata_c_turbo";
                    }
                }
            }
        }
    }

    // 2. Fallback to Massive
    if (count == 0 && strlen(s.massive_key) > 0) {
        count = fetch_massive(ticker, interval, periods, s.massive_key, bars);
        if (count > 0) {
            source = "massive_c_turbo";
        }
    }

    if (count == 0) {
        fprintf(stderr, "No values found from any API source\n");
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
        
        printf("  \"time_series\": [");
        for (int i = 0; i < count; i++) {
            printf("{\"t\": %lld, \"c\": %.2f}%s", bars[i].timestamp, bars[i].close, (i == count - 1) ? "" : ",");
        }
        printf("],\n");
        
        printf("  \"source\": \"%s\"\n}\n", source);
    } else {
        printf("=== %s Breakout Analysis (%s, %d periods) ===\n", ticker, display, periods);
        printf("Last Known Market Price: $%.2f\n", current_price);
        printf("Source: %s\n\n", source);
        printf("## Upper Resistance Levels\n  1. $%.2f (+%.2f)\n  2. $%.2f (+%.2f)\n\n", u1, u1-current_price, u2, u2-current_price);
        printf("## Lower Support Levels\n  1. $%.2f (-%.2f)\n  2. $%.2f (-%.2f)\n\n", l1, current_price-l1, l2, current_price-l2);
        printf("## Breakout Probability\n- UP: %.1f%%\n- DOWN: %.1f%%\n", prob_up, prob_down);
    }

    free(bars);
    return 0;
}
