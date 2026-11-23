<?php

declare(strict_types=1);

namespace JalaSupport\Services;

use JalaSupport\Models\Ticket;
use WP_Error;

/**
 * Moves tickets between sites (for example local -> production) as JSON.
 *
 * Export writes a versioned document with the ticket fields only: no post
 * IDs, user IDs or internal meta of the target site are needed. Import runs
 * every ticket through TicketValidator and TicketRepository, so imported
 * tickets get new IDs, a chosen author and the same validation as tickets
 * created in the app. Each imported ticket remembers its origin
 * ("<source>#<source_id>"), which makes repeated imports skip duplicates.
 */
final class TicketTransfer
{
    public const FORMAT = 'jalasupport-tickets';

    public const VERSION = 1;

    public const MAX_TICKETS = 5000;

    private const MAX_SOURCE = 200;

    public function __construct(
        private readonly TicketRepository $tickets,
        private readonly TicketValidator $validator,
    ) {}

    /**
     * @param  iterable<Ticket>  $tickets
     * @return array{format: string, version: int, source: string, exported_at: string, count: int, tickets: list<array<string, mixed>>}
     */
    public function export(iterable $tickets, string $source): array
    {
        $items = [];

        foreach ($tickets as $ticket) {
            $items[] = [
                'source_id' => $ticket->id,
                'title' => $ticket->title,
                'description' => $ticket->description,
                'status' => $ticket->status->value,
                'priority' => $ticket->priority->value,
                'category' => $ticket->category->value,
                'customer_name' => $ticket->customerName,
                'customer_email' => $ticket->customerEmail,
                'ai_summary' => $ticket->aiSummary,
                'ai_suggested_reply' => $ticket->aiSuggestedReply,
                'created_at' => $ticket->toArray()['created_at'],
            ];
        }

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'source' => $source,
            'exported_at' => gmdate(DATE_ATOM),
            'count' => count($items),
            'tickets' => $items,
        ];
    }

    /**
     * @param  mixed  $payload  Decoded JSON document (untrusted).
     * @return array{created: list<array{source_id: int, id: int, title: string}>, skipped: list<array{source_id: int, title: string, reason: string}>, invalid: list<array{index: int, source_id: int, errors: array<string, string>}>}|WP_Error
     */
    public function import(mixed $payload, int $authorId, bool $dryRun = false): array|WP_Error
    {
        $document = $this->checkDocument($payload);

        if ($document instanceof WP_Error) {
            return $document;
        }

        [$source, $items] = $document;
        $result = ['created' => [], 'skipped' => [], 'invalid' => []];
        $seen = [];

        foreach ($items as $index => $item) {
            $sourceId = is_array($item) && is_int($item['source_id'] ?? null) && $item['source_id'] > 0 ? $item['source_id'] : 0;

            if (! is_array($item) || $sourceId === 0) {
                $result['invalid'][] = ['index' => $index, 'source_id' => $sourceId, 'errors' => ['source_id' => 'A positive integer source_id is required.']];

                continue;
            }

            $data = $this->validator->validate($this->ticketInput($item));

            if ($data instanceof WP_Error) {
                $errors = $data->get_error_data()['errors'] ?? [];
                $result['invalid'][] = ['index' => $index, 'source_id' => $sourceId, 'errors' => is_array($errors) ? $errors : []];

                continue;
            }

            $importKey = $source . '#' . $sourceId;

            if (isset($seen[$importKey]) || $this->tickets->findIdByImportKey($importKey) !== null) {
                $result['skipped'][] = ['source_id' => $sourceId, 'title' => $data['title'], 'reason' => 'already imported'];

                continue;
            }

            $seen[$importKey] = true;

            if ($dryRun) {
                $result['created'][] = ['source_id' => $sourceId, 'id' => 0, 'title' => $data['title']];

                continue;
            }

            $ticket = $this->tickets->create($data, $authorId, [
                'import_key' => $importKey,
                'created_at' => $this->createdAt($item['created_at'] ?? null),
            ]);

            if ($ticket instanceof WP_Error) {
                $result['invalid'][] = ['index' => $index, 'source_id' => $sourceId, 'errors' => ['ticket' => $ticket->get_error_message()]];

                continue;
            }

            $result['created'][] = ['source_id' => $sourceId, 'id' => $ticket->id, 'title' => $ticket->title];
        }

        return $result;
    }

    /**
     * @return array{0: string, 1: list<mixed>}|WP_Error
     */
    private function checkDocument(mixed $payload): array|WP_Error
    {
        if (! is_array($payload) || ($payload['format'] ?? null) !== self::FORMAT) {
            return new WP_Error('jalasupport_import_format', sprintf('Not a %s export file.', self::FORMAT));
        }

        if (($payload['version'] ?? null) !== self::VERSION) {
            return new WP_Error('jalasupport_import_version', sprintf('Unsupported export version (expected %d).', self::VERSION));
        }

        $source = is_string($payload['source'] ?? null) ? sanitize_text_field($payload['source']) : '';

        if ($source === '' || mb_strlen($source) > self::MAX_SOURCE) {
            return new WP_Error('jalasupport_import_source', 'The export file has no valid source.');
        }

        $items = $payload['tickets'] ?? null;

        if (! is_array($items) || ! array_is_list($items)) {
            return new WP_Error('jalasupport_import_tickets', 'The export file has no ticket list.');
        }

        if (count($items) > self::MAX_TICKETS) {
            return new WP_Error('jalasupport_import_size', sprintf('Too many tickets in one file (maximum %d).', self::MAX_TICKETS));
        }

        return [$source, $items];
    }

    /**
     * Only the known ticket fields reach the validator.
     *
     * @param  array<mixed>  $item
     * @return array<string, mixed>
     */
    private function ticketInput(array $item): array
    {
        $fields = ['title', 'description', 'status', 'priority', 'category', 'customer_name', 'customer_email', 'ai_summary', 'ai_suggested_reply'];

        return array_intersect_key($item, array_flip($fields));
    }

    /**
     * Original creation time as GMT "Y-m-d H:i:s"; null (now) when missing,
     * invalid or in the future.
     */
    private function createdAt(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $date = date_create_immutable($value);

        if ($date === false || $date->getTimestamp() > time()) {
            return null;
        }

        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
