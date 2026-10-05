<?php

namespace App\Concerns;

use App\Support\Reports\Reports;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Shared date-range state for the admin report pages.
 */
trait HasReportRange
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mountHasReportRange(): void
    {
        $this->authorize('admin');

        $range = $this->reports()->range;
        $this->from = $range->from->toDateString();
        $this->to = $range->to->toDateString();
    }

    #[Computed]
    public function reports(): Reports
    {
        return Reports::make($this->from, $this->to);
    }

    /**
     * @return array{from: string, to: string}
     */
    public function exportParams(): array
    {
        return ['from' => $this->reports()->range->from->toDateString(), 'to' => $this->reports()->range->to->toDateString()];
    }
}
