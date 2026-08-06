<?php

namespace App\Services;

use App\Models\StarterKit;
use App\Models\StarterKitPendingChange;
use Illuminate\Support\Facades\Storage;

class StarterKitPendingChangeService
{
    public function submit(StarterKit $kit, int $profileId, array $fields): StarterKitPendingChange
    {
        $existing = StarterKitPendingChange::where('starter_kit_id', $kit->id)
            ->pending()
            ->first();

        $changes = $existing ? $existing->changeset : [];
        $original = $existing ? $existing->original : [];
        $bundled = $kit->status === 0;

        foreach ($fields as $field => $value) {
            if (
                isset($changes[$field]) &&
                $changes[$field]['status'] === 'pending' &&
                in_array($field, StarterKitPendingChange::MEDIA_FIELDS)
            ) {
                $oldPath = $changes[$field]['value'];
                if ($oldPath && Storage::disk('s3')->exists($oldPath)) {
                    Storage::disk('s3')->delete($oldPath);
                }
            }

            $changes[$field] = [
                'value' => is_array($value) ? $value['value'] : $value,
                'preview_url' => is_array($value) ? ($value['preview_url'] ?? null) : null,
                'status' => 'pending',
                'rejection_reason' => null,
            ];

            if (! isset($original[$field])) {
                $urlField = str_replace('_path', '_url', $field);
                $original[$field] = [
                    'value' => $kit->{$field},
                    'preview_url' => $kit->{$urlField} ?? null,
                ];
            }
        }

        if ($existing) {
            $existing->update([
                'profile_id' => $profileId,
                'changeset' => $changes,
                'original' => $original,
                'status' => 'pending',
                'bundled_with_kit_review' => $bundled,
            ]);
            $existing->refresh();

            return $existing;
        }

        return StarterKitPendingChange::create([
            'starter_kit_id' => $kit->id,
            'profile_id' => $profileId,
            'original' => $original,
            'changeset' => $changes,
            'status' => 'pending',
            'bundled_with_kit_review' => $bundled,
        ]);
    }

    public function approveField(StarterKitPendingChange $changeset, string $field, int $adminProfileId): void
    {
        $this->setFieldStatus($changeset, $field, 'approved');
        $this->markInReview($changeset, $adminProfileId);

        $changeset->refresh();

        if ($changeset->fresh()->allFieldsReviewed()) {
            $this->apply($changeset);
        }
    }

    public function rejectField(
        StarterKitPendingChange $changeset,
        string $field,
        int $adminProfileId,
        ?string $reason = null
    ): void {
        $this->setFieldStatus($changeset, $field, 'rejected', $reason);

        if (in_array($field, StarterKitPendingChange::MEDIA_FIELDS)) {
            $path = $changeset->changeset[$field]['value'];
            if ($path && Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
            }
        }

        $this->markInReview($changeset, $adminProfileId);

        if ($changeset->fresh()->allFieldsReviewed()) {
            $this->apply($changeset);
        }
    }

    public function apply(StarterKitPendingChange $changeset): void
    {
        $changeset->refresh();

        if (! $changeset->allFieldsReviewed()) {
            return;
        }

        $kit = $changeset->starterKit;
        $updates = [];
        $changes = $changeset->changeset;

        foreach ($changes as $field => $data) {
            if ($data['status'] === 'applied') {
                continue;
            }

            if ($data['status'] !== 'approved') {
                continue;
            }

            $isDelete = ($data['action'] ?? null) === 'delete';

            if ($isDelete) {
                if ($kit->{$field} && Storage::disk('s3')->exists($kit->{$field})) {
                    Storage::disk('s3')->delete($kit->{$field});
                }
                $updates[$field] = null;
                $updates[str_replace('_path', '_url', $field)] = null;
            } elseif (in_array($field, StarterKitPendingChange::MEDIA_FIELDS)) {
                if ($kit->{$field} && Storage::disk('s3')->exists($kit->{$field})) {
                    Storage::disk('s3')->delete($kit->{$field});
                }

                $finalPath = $this->moveToPermanent($data['value'], $field, $kit->id);
                $finalUrl = Storage::disk('s3')->url($finalPath);

                $updates[$field] = $finalPath;
                $updates[str_replace('_path', '_url', $field)] = $finalUrl;
            } else {
                $updates[$field] = $data['value'];
            }

            $changes[$field]['status'] = 'applied';
        }

        $changeset->update(['changeset' => $changes]);

        if (! empty($updates)) {
            /** @phpstan-ignore argument.type */
            $kit->update($updates);
            app(StarterKitService::class)->forget($kit->id);
        }

        if ($changeset->fresh()->allFieldsReviewed()) {
            $changeset->update([
                'status' => $changeset->fresh()->hasAnyRejectedFields() ? 'rejected' : 'applied',
                'applied_at' => now(),
            ]);
        }
    }

    private function setFieldStatus(
        StarterKitPendingChange $changeset,
        string $field,
        string $status,
        ?string $reason = null
    ): void {
        $changes = $changeset->changeset;
        $changes[$field]['status'] = $status;
        $changes[$field]['rejection_reason'] = $reason;
        $changeset->update(['changeset' => $changes]);
    }

    private function markInReview(StarterKitPendingChange $changeset, int $adminProfileId): void
    {
        if (in_array($changeset->status, ['pending', 'rejected', 'applied'])) {
            $changeset->update([
                'status' => 'in_review',
                'reviewed_by' => $adminProfileId,
            ]);
        }
    }

    public function hasPendingField(StarterKit $kit, string $field): bool
    {
        return StarterKitPendingChange::where('starter_kit_id', $kit->id)
            ->pending()
            ->whereJsonContainsKey("changeset->{$field}")
            ->where("changeset->{$field}->status", 'pending') // @phpstan-ignore argument.type
            ->exists();
    }

    public function moveToPermanent(string $tempPath, string $field, int $kitId): string
    {
        if (! str_starts_with($tempPath, 'starterkit/')) {
            return '';
        }

        $fieldName = match ($field) {
            'header_path' => 'header',
            'icon_path' => 'icon',
            default => 'media'
        };

        $ext = pathinfo($tempPath, PATHINFO_EXTENSION);
        $rand = random_int(1, 9999);
        $finalPath = "starterkit/{$kitId}/{$fieldName}-".$rand.".{$ext}";

        Storage::disk('s3')->copy($tempPath, $finalPath);
        Storage::disk('s3')->delete($tempPath);

        Storage::disk('s3')->setVisibility($finalPath, 'public');

        return $finalPath;
    }

    public function deleteMedia(StarterKit $kit, int $profileId, string $field): void
    {
        $existing = StarterKitPendingChange::where('starter_kit_id', $kit->id)
            ->pending()
            ->first();

        $changes = $existing ? $existing->changeset : [];
        $original = $existing ? $existing->original : [];

        if (
            isset($changes[$field]) &&
            $changes[$field]['status'] === 'pending' &&
            in_array($field, StarterKitPendingChange::MEDIA_FIELDS)
        ) {
            $oldPath = $changes[$field]['value'];
            if ($oldPath && Storage::disk('s3')->exists($oldPath)) {
                Storage::disk('s3')->delete($oldPath);
            }
        }

        $currentValue = $kit->{$field};
        if (! $currentValue) {
            unset($changes[$field], $original[$field]);
        } else {
            if (! isset($original[$field])) {
                $urlField = str_replace('_path', '_url', $field);
                $original[$field] = [
                    'value' => $kit->{$field},
                    'preview_url' => $kit->{$urlField} ?? null,
                ];
            }

            $changes[$field] = [
                'value' => null,
                'preview_url' => null,
                'action' => 'delete',
                'status' => 'pending',
                'rejection_reason' => null,
            ];
        }

        if (empty($changes)) {
            $existing?->delete();

            return;
        }

        if ($existing) {
            $existing->update([
                'profile_id' => $profileId,
                'changeset' => $changes,
                'original' => $original,
                'status' => 'pending',
                'bundled_with_kit_review' => false,
            ]);

            return;
        }

        StarterKitPendingChange::create([
            'starter_kit_id' => $kit->id,
            'profile_id' => $profileId,
            'original' => $original,
            'changeset' => $changes,
            'status' => 'pending',
        ]);
    }
}
