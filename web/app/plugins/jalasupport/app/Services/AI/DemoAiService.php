<?php

declare(strict_types=1);

namespace JalaSupport\Services\AI;

use JalaSupport\Models\Ticket;
use JalaSupport\Models\TicketCategory;
use JalaSupport\Models\TicketPriority;

/**
 * Sample AI answers for public demos (AI_PROVIDER=demo).
 *
 * Simple keyword rules stand in for a language model: no network requests,
 * no API key and no cost. The provider name and every summary say that the
 * answers are samples, so demo users are never misled.
 */
final class DemoAiService implements AiProvider
{
    private const SUMMARY_LENGTH = 160;

    /**
     * Keywords per category, checked in this order.
     *
     * @var array<string, list<string>>
     */
    private const CATEGORY_KEYWORDS = [
        'billing' => ['invoice', 'billing', 'payment', 'refund', 'charged', 'charge', 'subscription', 'receipt', 'price'],
        'account_issues' => ['password', 'log in', 'login', 'sign in', 'locked', 'account', 'two-factor', '2fa', 'username'],
        'technical_support' => ['error', 'bug', 'crash', 'broken', 'not working', "doesn't work", 'slow', 'install', 'upload', 'timeout', 'api'],
        'general_inquiry' => ['question', 'how do', 'how can', 'information', 'wondering', 'feature', 'roadmap'],
    ];

    /**
     * Keywords per priority, checked in this order.
     *
     * @var array<string, list<string>>
     */
    private const PRIORITY_KEYWORDS = [
        'urgent' => ['urgent', 'asap', 'immediately', 'outage', 'down for everyone', 'critical', 'data loss', 'security'],
        'high' => ['cannot', "can't", 'unable', 'locked', 'error', 'failed', 'broken', 'charged twice'],
        'low' => ['question', 'wondering', 'feature', 'suggestion', 'when will', 'just curious'],
    ];

    public function name(): string
    {
        return __('Demo AI (sample answers)', 'jalasupport');
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function analyze(Ticket $ticket): TicketAnalysis
    {
        $category = $this->category($ticket);

        return new TicketAnalysis(
            $category,
            $this->priority($ticket),
            $this->summary($ticket),
            $this->reply($ticket, $category),
        );
    }

    public function suggestReply(Ticket $ticket): TicketAnalysis
    {
        return new TicketAnalysis(null, null, '', $this->reply($ticket, $this->category($ticket)));
    }

    public function describe(): array
    {
        return [
            __('Mode', 'jalasupport') => __('Demo: keyword rules, no language model', 'jalasupport'),
            __('External requests', 'jalasupport') => __('None', 'jalasupport'),
            __('Authentication', 'jalasupport') => __('Not needed', 'jalasupport'),
        ];
    }

    private function category(Ticket $ticket): TicketCategory
    {
        $value = $this->firstMatch($ticket, self::CATEGORY_KEYWORDS);

        return $value !== null ? TicketCategory::from($value) : TicketCategory::Other;
    }

    private function priority(Ticket $ticket): TicketPriority
    {
        $value = $this->firstMatch($ticket, self::PRIORITY_KEYWORDS);

        return $value !== null ? TicketPriority::from($value) : TicketPriority::Normal;
    }

    /**
     * @param  array<string, list<string>>  $rules
     */
    private function firstMatch(Ticket $ticket, array $rules): ?string
    {
        $text = mb_strtolower($ticket->title . "\n" . $ticket->description);

        foreach ($rules as $value => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function summary(Ticket $ticket): string
    {
        $text = trim((string) preg_replace('/\s+/', ' ', $ticket->description));
        $sentence = (string) preg_split('/(?<=[.!?])\s/', $text, 2)[0];

        if ($sentence === '') {
            $sentence = $ticket->title;
        }

        if (mb_strlen($sentence) > self::SUMMARY_LENGTH) {
            $sentence = rtrim(mb_substr($sentence, 0, self::SUMMARY_LENGTH - 1)) . '…';
        }

        $customer = $ticket->customerName !== '' ? $ticket->customerName : __('The customer', 'jalasupport');

        /* translators: 1: customer name, 2: first sentence of the ticket */
        return sprintf(__('[Demo] %1$s writes: %2$s', 'jalasupport'), $customer, $sentence);
    }

    private function reply(Ticket $ticket, TicketCategory $category): string
    {
        $firstName = strtok(trim($ticket->customerName), ' ');
        $greeting = $firstName !== false
            /* translators: %s: customer first name */
            ? sprintf(__('Hi %s,', 'jalasupport'), $firstName)
            : __('Hello,', 'jalasupport');

        $body = match ($category) {
            TicketCategory::Billing => __('Thank you for reaching out about your billing question. I have checked your account and will make sure any incorrect charge is corrected. You will receive a confirmation by email once it is done.', 'jalasupport'),
            TicketCategory::AccountIssues => __('Sorry for the trouble accessing your account. Please try the "Forgot password" link on the sign-in page; the reset link is valid for one hour. If it still does not work, reply to this message and I will unlock the account for you.', 'jalasupport'),
            TicketCategory::TechnicalSupport => __('Thank you for the detailed report. I have passed it to our technical team. Could you tell us which browser and device you use, and the time the problem last happened? That helps us reproduce it quickly.', 'jalasupport'),
            TicketCategory::GeneralInquiry => __('Thank you for your question. I have added the details below and linked the relevant help article. Let me know if anything is unclear.', 'jalasupport'),
            TicketCategory::Other => __('Thank you for contacting us. I have read your message and will get back to you with an update as soon as possible.', 'jalasupport'),
        };

        return $greeting . "\n\n" . $body . "\n\n" . __('Best regards,', 'jalasupport') . "\n" . __('The support team', 'jalasupport');
    }
}
