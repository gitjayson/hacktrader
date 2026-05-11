<?php
/**
 * correlate.php - Intelligent Market Correlation Endpoint
 * v0.13.2 - deterministic direct/sector/baseline fallback
 */

$ticker = strtoupper(trim($_GET['ticker'] ?? 'TSLA'));
$correlationsFile = __DIR__ . '/correlations.json';

function respond_json($payload, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function normalize_relation($value): string {
    return strtolower((string) $value) === 'negative' ? 'negative' : 'positive';
}

function push_unique_symbol(array &$result, string $symbol, string $relation = 'positive'): void {
    $symbol = strtoupper(trim($symbol));
    if ($symbol === '') {
        return;
    }
    foreach ($result as $item) {
        if (($item['symbol'] ?? null) === $symbol) {
            return;
        }
    }
    $result[] = [
        'symbol' => $symbol,
        'relation' => normalize_relation($relation),
    ];
}

function append_symbol_list(array &$result, array $symbols, string $relation = 'positive'): void {
    foreach ($symbols as $symbol) {
        push_unique_symbol($result, $symbol, $relation);
    }
}

function infer_sector_fallback(string $ticker): array {
    $energyTickers = ['WTI', 'USO', 'XOM', 'CVX', 'SLB', 'OXY', 'HAL', 'BP', 'SHEL'];
    $cryptoTickers = ['BTC', 'ETH', 'COIN', 'MSTR', 'IBIT', 'BITO'];
    $chinaTickers = ['BABA', 'JD', 'PDD', 'BIDU', 'KWEB', 'FXI'];
    $rateTickers = ['TLT', 'IEF', 'SHY', 'HYG', 'LQD'];
    $goldTickers = ['GLD', 'SLV', 'GDX', 'NEM', 'AEM'];

    if (in_array($ticker, ['QQQ', 'XLK', 'SMH', 'VGT', 'NVDA', 'AMD', 'TSLA', 'AAPL', 'MSFT', 'META', 'AMZN', 'GOOGL', 'NFLX', 'AVGO', 'ADBE'], true)) {
        return [
            ['symbols' => ['QQQ', 'XLK', 'SMH', 'VGT', 'SOXX', 'SPY'], 'relation' => 'positive'],
            ['symbols' => ['UUP', 'TLT'], 'relation' => 'negative'],
        ];
    }

    if (in_array($ticker, ['XLF', 'KBE', 'KRE', 'JPM', 'BAC', 'GS', 'MS', 'WFC', 'SCHW'], true)) {
        return [
            ['symbols' => ['XLF', 'KBE', 'KRE', 'SPY', 'DIA'], 'relation' => 'positive'],
            ['symbols' => ['TLT', 'UUP'], 'relation' => 'negative'],
        ];
    }

    if (in_array($ticker, ['XLE', 'XOP', 'UNG'], true) || in_array($ticker, $energyTickers, true)) {
        return [
            ['symbols' => ['XLE', 'XOP', 'UNG', 'WTI', 'SPY'], 'relation' => 'positive'],
            ['symbols' => ['UUP', 'TLT'], 'relation' => 'negative'],
        ];
    }

    if (in_array($ticker, ['XLV', 'VHT', 'LLY', 'JNJ', 'PFE', 'MRK', 'UNH'], true)) {
        return [
            ['symbols' => ['XLV', 'VHT', 'SPY', 'IWM'], 'relation' => 'positive'],
            ['symbols' => ['UUP'], 'relation' => 'negative'],
        ];
    }

    if (in_array($ticker, ['XLY', 'XLP', 'VCR', 'COST', 'WMT', 'HD', 'LOW', 'NKE', 'SBUX'], true)) {
        return [
            ['symbols' => ['XLY', 'XLP', 'VCR', 'SPY', 'QQQ'], 'relation' => 'positive'],
            ['symbols' => ['UUP', 'TLT'], 'relation' => 'negative'],
        ];
    }

    if (in_array($ticker, ['XLI', 'ITA', 'CAT', 'DE', 'GE', 'BA', 'LMT', 'RTX'], true)) {
        return [
            ['symbols' => ['XLI', 'ITA', 'DIA', 'SPY'], 'relation' => 'positive'],
            ['symbols' => ['TLT', 'UUP'], 'relation' => 'negative'],
        ];
    }

    if (in_array($ticker, $cryptoTickers, true)) {
        return [
            ['symbols' => ['COIN', 'MSTR', 'QQQ', 'SMH'], 'relation' => 'positive'],
            ['symbols' => ['UUP', 'TLT'], 'relation' => 'negative'],
        ];
    }

    if (in_array($ticker, $chinaTickers, true)) {
        return [
            ['symbols' => ['KWEB', 'FXI', 'EEM', 'SPY'], 'relation' => 'positive'],
            ['symbols' => ['UUP'], 'relation' => 'negative'],
        ];
    }

    if (in_array($ticker, $rateTickers, true)) {
        return [
            ['symbols' => ['TLT', 'IEF', 'SHY', 'LQD'], 'relation' => 'positive'],
            ['symbols' => ['SPY', 'QQQ', 'UUP'], 'relation' => 'negative'],
        ];
    }

    if (in_array($ticker, $goldTickers, true)) {
        return [
            ['symbols' => ['GLD', 'SLV', 'GDX', 'TLT'], 'relation' => 'positive'],
            ['symbols' => ['UUP', 'SPY'], 'relation' => 'negative'],
        ];
    }

    return [
        ['symbols' => ['SPY', 'QQQ', 'IWM', 'DIA', 'XLK', 'XLF'], 'relation' => 'positive'],
        ['symbols' => ['UUP', 'TLT'], 'relation' => 'negative'],
    ];
}

if (!file_exists($correlationsFile)) {
    respond_json(['error' => 'Correlation data missing'], 500);
}

$data = json_decode(file_get_contents($correlationsFile), true);
if (!is_array($data)) {
    respond_json(['error' => 'Malformed correlation data'], 500);
}

$result = [];

if (isset($data[$ticker]) && is_array($data[$ticker])) {
    foreach ($data[$ticker] as $item) {
        if (!is_array($item) || empty($item['symbol'])) {
            continue;
        }
        push_unique_symbol($result, (string) $item['symbol'], (string) ($item['relation'] ?? 'positive'));
    }
} else {
    foreach (infer_sector_fallback($ticker) as $bucket) {
        append_symbol_list($result, $bucket['symbols'] ?? [], $bucket['relation'] ?? 'positive');
    }
}

// v0.13.2 — Baseline expanded from 12 to 16 unique tickers so the basket
// can still fill all 12 slots after the focus ticker is filtered out (and
// after any duplicates collapse via push_unique_symbol). Added XLV
// (healthcare) and XLE (energy) to the positive sector list, plus IEF
// (mid-duration treasuries) and HYG (high-yield credit) to the negative
// list — both common macro indicators.
$baseline = [
    ['symbols' => ['SPY', 'QQQ', 'IWM', 'DIA'], 'relation' => 'positive'],
    ['symbols' => ['XLK', 'XLF', 'XLY', 'XLI', 'SMH', 'XLV', 'XLE'], 'relation' => 'positive'],
    ['symbols' => ['TLT', 'UUP', 'GLD', 'IEF', 'HYG'], 'relation' => 'negative'],
];

foreach ($baseline as $bucket) {
    append_symbol_list($result, $bucket['symbols'], $bucket['relation']);
}

// v0.13.2 — Filter out the focus ticker. A stock cannot correlate with
// itself, and having it appear in its own basket is visually confusing
// (the focus node is already in the center). Happens when the focus is
// one of the general-baseline tickers (SPY, QQQ, etc.) that get appended.
$result = array_values(array_filter($result, function ($item) use ($ticker) {
    return ($item['symbol'] ?? '') !== $ticker;
}));

$result = array_values(array_slice($result, 0, 12));
respond_json($result);
