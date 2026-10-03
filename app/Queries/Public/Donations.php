<?php

declare(strict_types=1);

namespace App\Queries\Public;

use App\Models\Donation;

/**
 * Minimal public view of a donation for the thank-you page (port of getDonationSummary in
 * lib/payments/service.ts): no email, no provider data. The status always comes from the DB, never
 * from the URL. ENGAGEMENT owns payments; this read-only DTO is all the PUBLIC page needs.
 */
final class Donations
{
    /**
     * @return array{reference: string, status: string, amount: int, currency: string, provider: string, firstName: string|null, isTestMode: bool, frequency: string, project: list<array{locale: string, title: string, slug: string}>}|null
     */
    public static function summary(string $reference): ?array
    {
        if (preg_match('/^SKL-\d{4}-[A-Z2-9]{8}$/', $reference) !== 1) {
            return null;
        }
        $d = Donation::query()->where('reference', $reference)->with('project.translations')->first();
        if ($d === null) {
            return null;
        }
        $data = is_array($d->providerData) ? $d->providerData : [];
        $project = [];
        foreach ($d->project !== null ? $d->project->translations : [] as $t) {
            $project[] = ['locale' => Shared::localeOf($t), 'title' => (string) $t->title, 'slug' => (string) $t->slug];
        }
        $first = null;
        if (! $d->isAnonymous && is_string($d->donorName) && $d->donorName !== '') {
            $first = preg_split('/\s+/', trim($d->donorName))[0] ?? null;
        }

        return [
            'reference' => (string) $d->reference,
            'status' => (string) Shared::enumValue($d->status),
            'amount' => (int) $d->amount,
            'currency' => (string) $d->currency,
            'provider' => (string) Shared::enumValue($d->provider),
            'firstName' => $first,
            'isTestMode' => (bool) $d->isTestMode,
            'frequency' => ($data['frequency'] ?? null) === 'monthly' ? 'monthly' : 'once',
            'project' => $project,
        ];
    }
}
