<?php

declare(strict_types=1);

namespace JalaSupport\Models;

enum TicketCategory: string
{
    use HasOptions;

    case TechnicalSupport = 'technical_support';
    case AccountIssues = 'account_issues';
    case Billing = 'billing';
    case GeneralInquiry = 'general_inquiry';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::TechnicalSupport => __('Technical Support', 'jalasupport'),
            self::AccountIssues => __('Account Issues', 'jalasupport'),
            self::Billing => __('Billing', 'jalasupport'),
            self::GeneralInquiry => __('General Inquiry', 'jalasupport'),
            self::Other => __('Other', 'jalasupport'),
        };
    }

    public static function default(): self
    {
        return self::GeneralInquiry;
    }
}
