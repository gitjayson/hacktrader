<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for api_auth.php — authenticate_api_key + log_api_usage.
 *
 * Both functions take an optional path parameter (added for testability)
 * so we can drop fixtures into a temp file and inject the path.
 */
final class ApiAuthTest extends TestCase
{
    private string $tmpAccounts;
    private string $tmpUsage;

    protected function setUp(): void
    {
        $this->tmpAccounts = tempnam(sys_get_temp_dir(), 'accts_');
        $this->tmpUsage   = tempnam(sys_get_temp_dir(), 'usage_');
        // Start clean: tempnam creates an empty file; remove it so file_exists() is false.
        @unlink($this->tmpAccounts);
        @unlink($this->tmpUsage);
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpAccounts);
        @unlink($this->tmpUsage);
    }

    // ---- authenticate_api_key ------------------------------------------------

    public function testAuthenticateReturnsFalseForEmptyKey(): void
    {
        $this->assertFalse(authenticate_api_key('', $this->tmpAccounts));
        $this->assertFalse(authenticate_api_key(null, $this->tmpAccounts));
        $this->assertFalse(authenticate_api_key('0', $this->tmpAccounts));  // PHP truthiness gotcha
    }

    public function testAuthenticateReturnsFalseWhenAccountsFileMissing(): void
    {
        $missing = sys_get_temp_dir() . '/this-file-does-not-exist-' . uniqid();
        $this->assertFalse(authenticate_api_key('any-key', $missing));
    }

    public function testAuthenticateReturnsFalseForUnknownKey(): void
    {
        file_put_contents(
            $this->tmpAccounts,
            json_encode(['known-key' => ['owner' => 'alice']])
        );
        $this->assertFalse(authenticate_api_key('unknown-key', $this->tmpAccounts));
    }

    public function testAuthenticateReturnsAccountForValidKey(): void
    {
        $accounts = [
            'valid-key' => ['owner' => 'jay', 'tier' => 'premium'],
            'other'     => ['owner' => 'bob', 'tier' => 'free'],
        ];
        file_put_contents($this->tmpAccounts, json_encode($accounts));

        $result = authenticate_api_key('valid-key', $this->tmpAccounts);
        $this->assertSame(['owner' => 'jay', 'tier' => 'premium'], $result);
    }

    public function testAuthenticateReturnsFalseForCorruptJson(): void
    {
        file_put_contents($this->tmpAccounts, '{not valid json');
        $this->assertFalse(authenticate_api_key('any-key', $this->tmpAccounts));
    }

    public function testAuthenticateReturnsFalseWhenJsonIsNotAnObject(): void
    {
        // Valid JSON, but it's a list — not the {key: account} shape we expect.
        file_put_contents($this->tmpAccounts, json_encode(['not', 'an', 'object']));
        $this->assertFalse(authenticate_api_key('any-key', $this->tmpAccounts));
    }

    public function testAuthenticateReturnsFalseWhenFileIsEmpty(): void
    {
        file_put_contents($this->tmpAccounts, '');
        $this->assertFalse(authenticate_api_key('any-key', $this->tmpAccounts));
    }

    // ---- log_api_usage -------------------------------------------------------

    public function testLogApiUsageWritesEntry(): void
    {
        log_api_usage('test-key', 'GET /api', 'TSLA', $this->tmpUsage);

        $this->assertFileExists($this->tmpUsage);
        $entries = json_decode(file_get_contents($this->tmpUsage), true);

        $this->assertIsArray($entries);
        $this->assertCount(1, $entries);
        $this->assertSame('test-key', $entries[0]['api_key']);
        $this->assertSame('GET /api', $entries[0]['endpoint']);
        $this->assertSame('TSLA', $entries[0]['ticker']);
        $this->assertArrayHasKey('timestamp', $entries[0]);
    }

    public function testLogApiUsageDefaultsTickerToEmpty(): void
    {
        log_api_usage('k', '/health', '', $this->tmpUsage);
        $entries = json_decode(file_get_contents($this->tmpUsage), true);
        $this->assertSame('', $entries[0]['ticker']);
    }

    public function testLogApiUsageAppendsToExistingFile(): void
    {
        log_api_usage('first',  '/a', '', $this->tmpUsage);
        log_api_usage('second', '/b', '', $this->tmpUsage);

        $entries = json_decode(file_get_contents($this->tmpUsage), true);
        $this->assertCount(2, $entries);
        $this->assertSame('first',  $entries[0]['api_key']);
        $this->assertSame('second', $entries[1]['api_key']);
    }

    public function testLogApiUsageCapsAt1000Entries(): void
    {
        // Pre-populate with exactly 1000 dummy entries.
        $existing = [];
        for ($i = 0; $i < 1000; $i++) {
            $existing[] = [
                'api_key'   => "k$i",
                'endpoint'  => '/x',
                'ticker'    => '',
                'timestamp' => date('c'),
            ];
        }
        file_put_contents($this->tmpUsage, json_encode($existing));

        log_api_usage('newest', '/new', '', $this->tmpUsage);
        $entries = json_decode(file_get_contents($this->tmpUsage), true);

        $this->assertCount(1000, $entries);
        // Oldest entry (k0) should have been dropped, k1 should now lead.
        $this->assertSame('k1', $entries[0]['api_key']);
        // Newest entry should be at the tail.
        $this->assertSame('newest', $entries[999]['api_key']);
    }

    public function testLogApiUsageSilentlyTolerantOfCorruptExistingFile(): void
    {
        file_put_contents($this->tmpUsage, '{not json');
        // Should not throw — corrupt content is treated as "no prior entries".
        log_api_usage('first-after-corruption', '/x', '', $this->tmpUsage);

        $entries = json_decode(file_get_contents($this->tmpUsage), true);
        $this->assertIsArray($entries);
        $this->assertCount(1, $entries);
        $this->assertSame('first-after-corruption', $entries[0]['api_key']);
    }
}
