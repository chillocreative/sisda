<?php

namespace App\Services;

use App\Models\User;

/**
 * Masks sensitive voter fields for records submitted by a plain 'user'
 * role account. Viewers with admin, super_user, or super_admin roles can
 * see the real values; everyone else gets '****' and cannot overwrite
 * the fields on update.
 */
class VoterDataMasker
{
    public const MASK = '****';

    public const SENSITIVE_FIELDS = [
        // Maklumat Peribadi (excluding Nama)
        'no_ic',
        'umur',
        'bangsa',
        'no_tel',
        // Maklumat Alamat (entire card)
        'alamat',
        'poskod',
        'negeri',
        'bandar',
        // Maklumat Isi Rumah (only this field)
        'pendapatan_isi_rumah',
        // Note: kad_pengenalan and nota are intentionally NOT masked.
        // Document uploads and notes must be visible and editable to
        // every role so they can build up a visible history across
        // bantuan events.
    ];

    /**
     * A record is locked if it was submitted by someone whose current role
     * is 'user'. Requires the submittedBy relationship to be loaded.
     */
    public static function isLocked($record): bool
    {
        if (! $record) {
            return false;
        }
        $submitter = $record->submittedBy ?? null;
        return $submitter && $submitter->role === 'user';
    }

    /**
     * Viewers allowed to see / edit the sensitive fields of a locked record.
     */
    public static function canUnmask(?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }
        return $viewer->isSuperUser() || $viewer->isSuperAdmin() || $viewer->isAdmin();
    }

    /**
     * Return an array representation of the record with sensitive fields
     * replaced by the mask when the viewer is not allowed to see them.
     * Non-sensitive fields pass through untouched.
     */
    public static function mask($record, ?User $viewer): array
    {
        if (! $record) {
            return [];
        }

        $array = method_exists($record, 'toArray') ? $record->toArray() : (array) $record;

        if (! self::isLocked($record) || self::canUnmask($viewer)) {
            return $array;
        }

        foreach (self::SENSITIVE_FIELDS as $field) {
            if (array_key_exists($field, $array)) {
                $array[$field] = self::MASK;
            }
        }

        return $array;
    }

    /**
     * Drop sensitive fields from a validated payload when the viewer is not
     * allowed to overwrite them. Call before Model::update(). Keeps the
     * existing DB value intact for every field the viewer may not touch.
     */
    public static function stripForbiddenWrites(array $validated, $record, ?User $viewer): array
    {
        if (! self::isLocked($record) || self::canUnmask($viewer)) {
            return $validated;
        }

        foreach (self::SENSITIVE_FIELDS as $field) {
            unset($validated[$field]);
        }

        return $validated;
    }
}
