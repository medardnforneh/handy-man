<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * A worker's on-site job report (build plan P5-04). Multipart: the report fields plus before/after
 * photos as `photos[i][file]` with `photos[i][kind]` and optional per-photo geo. Every photo is
 * EXIF-stripped on the way in.
 */
final class SubmitJobReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'max:5000'],
            'extra_charges_minor' => ['nullable', 'integer', 'min:0'],

            'materials' => ['nullable', 'array', 'max:100'],
            'materials.*.label' => ['required', 'string', 'max:255'],
            'materials.*.qty' => ['required', 'numeric', 'min:0'],
            'materials.*.unit_cost_minor' => ['required', 'integer', 'min:0'],

            'photos' => ['nullable', 'array', 'max:20'],
            'photos.*.file' => ['required', 'file', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
            'photos.*.kind' => ['required', 'in:before,after,issue'],
            'photos.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'photos.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<int, array{label: string, qty: int|float, unit_cost_minor: int}>
     */
    public function materials(): array
    {
        /** @var array<int, array{label: string, qty: int|float, unit_cost_minor: int}> $materials */
        $materials = $this->input('materials', []);

        return array_values($materials);
    }

    /**
     * @return array<int, array{file: UploadedFile, kind: string, latitude: ?float, longitude: ?float}>
     */
    public function photos(): array
    {
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $this->input('photos', []);
        $out = [];

        foreach (array_keys($rows) as $i) {
            $file = $this->file("photos.{$i}.file");
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $out[] = [
                'file' => $file,
                'kind' => (string) $this->input("photos.{$i}.kind"),
                'latitude' => $this->filled("photos.{$i}.latitude") ? (float) $this->input("photos.{$i}.latitude") : null,
                'longitude' => $this->filled("photos.{$i}.longitude") ? (float) $this->input("photos.{$i}.longitude") : null,
            ];
        }

        return $out;
    }
}
