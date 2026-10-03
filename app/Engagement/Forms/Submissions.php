<?php

declare(strict_types=1);

namespace App\Engagement\Forms;

use App\Engagement\Mail\Notify;
use App\Models\ContactSubmission;
use App\Models\MembershipApplication;
use App\Models\PartnerInquiry;
use App\Models\VolunteerApplication;
use Illuminate\Support\Carbon;

/**
 * Persist a validated public submission (port of lib/forms/submit.ts). `consent` is recorded as a
 * server-side timestamp (GDPR accountability). Returns the new row id.
 */
final class Submissions
{
    /** @param  array<string, mixed>  $d  output of FormSchemas::parse()->data */
    public static function save(string $type, array $d, string $locale, string $ipHash): string
    {
        $consentAt = Carbon::now('UTC');
        $row = match ($type) {
            'contact' => ContactSubmission::query()->create([
                'locale' => $locale,
                'name' => $d['name'],
                'email' => $d['email'],
                'phone' => $d['phone'] ?? null,
                'subject' => $d['subject'] ?? null,
                'message' => $d['message'],
                'consentAt' => $consentAt,
                'ipHash' => $ipHash,
            ]),
            'volunteer' => VolunteerApplication::query()->create([
                'locale' => $locale,
                'firstName' => $d['firstName'],
                'lastName' => $d['lastName'],
                'email' => $d['email'],
                'phone' => $d['phone'] ?? null,
                'country' => $d['country'] ?? null,
                'city' => $d['city'] ?? null,
                'skills' => $d['skills'] ?? null,
                'availability' => $d['availability'] ?? null,
                'message' => $d['message'] ?? null,
                'consentAt' => $consentAt,
            ]),
            'membership' => MembershipApplication::query()->create([
                'locale' => $locale,
                'firstName' => $d['firstName'],
                'lastName' => $d['lastName'],
                'email' => $d['email'],
                'phone' => $d['phone'] ?? null,
                'address' => $d['address'] ?? null,
                'city' => $d['city'] ?? null,
                'country' => $d['country'] ?? null,
                'membershipType' => $d['membershipType'],
                'message' => $d['message'] ?? null,
                'consentAt' => $consentAt,
            ]),
            'partner' => PartnerInquiry::query()->create([
                'locale' => $locale,
                'organization' => $d['organization'],
                'contactName' => $d['contactName'],
                'email' => $d['email'],
                'phone' => $d['phone'] ?? null,
                'website' => $d['website'] ?? null,
                'partnershipType' => $d['partnershipType'] ?? null,
                'message' => $d['message'],
                'consentAt' => $consentAt,
            ]),
            default => throw new \InvalidArgumentException("Unknown form type {$type}"),
        };
        $id = (string) $row->getKey();
        Notify::team($type, $id);

        return $id;
    }
}
