<?php

declare(strict_types=1);

namespace JalaSupport\Console;

use JalaSupport\Models\Ticket;
use JalaSupport\Services\TicketRepository;
use JalaSupport\Services\TicketTransfer;
use JalaSupport\Support\Capabilities;
use WP_CLI;
use WP_Error;
use WP_User;

/**
 * Export and import support tickets (JSON), for example to copy tickets
 * from local development to production.
 *
 * Export files contain customer names and email addresses: keep them out of
 * Git (backups/ is ignored) and delete them after use.
 */
final class TicketsCommand
{
    private const MAX_FILE_BYTES = 20 * 1024 * 1024;

    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly TicketTransfer $transfer,
    ) {}

    /**
     * Export tickets as JSON.
     *
     * ## OPTIONS
     *
     * [--file=<path>]
     * : Write to this file instead of standard output.
     *
     * [--ids=<ids>]
     * : Comma-separated ticket IDs (default: all published tickets).
     *
     * ## EXAMPLES
     *
     *     wp jalasupport tickets export --file=backups/tickets.json
     *     wp jalasupport tickets export --ids=4,5 > tickets.json
     *
     * @param  list<string>  $args
     * @param  array<string, string>  $assoc
     */
    public function export(array $args, array $assoc): void
    {
        $tickets = isset($assoc['ids']) ? $this->ticketsById($assoc['ids']) : $this->tickets->all();
        $document = $this->transfer->export($tickets, (string) wp_parse_url(home_url(), PHP_URL_HOST));
        $json = wp_json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            WP_CLI::error('The tickets could not be encoded as JSON.');
        }

        if (! isset($assoc['file'])) {
            WP_CLI::line($json);

            return;
        }

        // Owner-only permissions where the filesystem supports them (customer data).
        $umask = umask(0077);
        $written = file_put_contents($assoc['file'], $json . "\n");
        umask($umask);

        if ($written === false) {
            WP_CLI::error(sprintf('Could not write %s.', $assoc['file']));
        }
        WP_CLI::success(sprintf('Exported %d tickets to %s (contains customer data: delete it after use).', $document['count'], $assoc['file']));
    }

    /**
     * Import tickets from a JSON export.
     *
     * Every ticket is validated like a ticket created in the app and gets a
     * new ID. Tickets imported before (same source site and ticket ID) are
     * skipped, so the same file can be imported again safely.
     *
     * ## OPTIONS
     *
     * <file>
     * : Export file to import.
     *
     * --author=<user>
     * : Login, email or ID of the user who becomes the author of the
     * imported tickets. Must have access to the support app.
     *
     * [--dry-run]
     * : Validate and show what would be imported without saving anything.
     *
     * ## EXAMPLES
     *
     *     wp jalasupport tickets import backups/tickets.json --author=admin --dry-run
     *     wp jalasupport tickets import backups/tickets.json --author=admin
     *
     * @param  list<string>  $args
     * @param  array<string, string|bool>  $assoc
     */
    public function import(array $args, array $assoc): void
    {
        $file = $args[0];
        $dryRun = ! empty($assoc['dry-run']);
        $author = $this->author((string) ($assoc['author'] ?? ''));

        if (! is_file($file) || ! is_readable($file)) {
            WP_CLI::error(sprintf('File not found or not readable: %s', $file));
        }

        if (filesize($file) > self::MAX_FILE_BYTES) {
            WP_CLI::error(sprintf('File is larger than %d MB.', self::MAX_FILE_BYTES / 1024 / 1024));
        }

        $payload = json_decode((string) file_get_contents($file), true, 32);

        if (json_last_error() !== JSON_ERROR_NONE) {
            WP_CLI::error('The file is not valid JSON: ' . json_last_error_msg());
        }

        $result = $this->transfer->import($payload, $author->ID, $dryRun);

        if ($result instanceof WP_Error) {
            WP_CLI::error($result->get_error_message());
        }

        foreach ($result['created'] as $row) {
            WP_CLI::log($dryRun
                ? sprintf('Would import #%d "%s"', $row['source_id'], $row['title'])
                : sprintf('Imported #%d -> ticket %d "%s"', $row['source_id'], $row['id'], $row['title']));
        }

        foreach ($result['skipped'] as $row) {
            WP_CLI::log(sprintf('Skipped #%d "%s" (%s)', $row['source_id'], $row['title'], $row['reason']));
        }

        foreach ($result['invalid'] as $row) {
            $errors = implode('; ', array_map(
                static fn(string $field, string $message): string => $field . ': ' . $message,
                array_keys($row['errors']),
                $row['errors'],
            ));
            WP_CLI::warning(sprintf('Invalid ticket #%d (entry %d): %s', $row['source_id'], $row['index'] + 1, $errors));
        }

        $summary = sprintf(
            '%s %d, skipped %d, invalid %d (author: %s).',
            $dryRun ? 'Dry run: would import' : 'Imported',
            count($result['created']),
            count($result['skipped']),
            count($result['invalid']),
            $author->user_login,
        );

        if ($result['invalid'] !== []) {
            WP_CLI::warning($summary);

            return;
        }

        WP_CLI::success($summary);
    }

    /**
     * @return list<Ticket>
     */
    private function ticketsById(string $ids): array
    {
        $tickets = [];

        foreach (array_filter(array_map('absint', explode(',', $ids))) as $id) {
            $ticket = $this->tickets->find($id);

            if ($ticket === null) {
                WP_CLI::error(sprintf('Ticket %d not found.', $id));
            }

            $tickets[] = $ticket;
        }

        return $tickets;
    }

    private function author(string $value): WP_User
    {
        if ($value === '') {
            WP_CLI::error('Choose the author of the imported tickets with --author=<login|email|id>.');
        }

        $user = ctype_digit($value)
            ? get_user_by('id', (int) $value)
            : (get_user_by('login', $value) ?: get_user_by('email', $value));

        if (! $user instanceof WP_User) {
            WP_CLI::error(sprintf('User not found: %s', $value));
        }

        if (! user_can($user, Capabilities::ACCESS_APP)) {
            WP_CLI::error(sprintf('User %s has no access to the support app.', $user->user_login));
        }

        return $user;
    }
}
