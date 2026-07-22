<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCode extends Model
{
    protected $fillable = [
        'company_id', 'code', 'label', 'color', 'system_key', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Fixed color palette. Each entry carries the Tailwind classes for the
     * on-screen grid and the hex pair for the dompdf export (dompdf can't
     * use Tailwind).
     */
    public const COLORS = [
        'green'  => ['tw' => 'bg-green-100 text-green-800',   'bg' => '#DCFCE7', 'text' => '#166534'],
        'slate'  => ['tw' => 'bg-slate-200 text-slate-600',   'bg' => '#E2E8F0', 'text' => '#475569'],
        'red'    => ['tw' => 'bg-red-100 text-red-700',       'bg' => '#FEE2E2', 'text' => '#B91C1C'],
        'amber'  => ['tw' => 'bg-amber-100 text-amber-800',   'bg' => '#FEF3C7', 'text' => '#92400E'],
        'yellow' => ['tw' => 'bg-yellow-100 text-yellow-800', 'bg' => '#FEF9C3', 'text' => '#854D0E'],
        'orange' => ['tw' => 'bg-orange-100 text-orange-800', 'bg' => '#FFEDD5', 'text' => '#9A3412'],
        'blue'   => ['tw' => 'bg-blue-100 text-blue-800',     'bg' => '#DBEAFE', 'text' => '#1E40AF'],
        'sky'    => ['tw' => 'bg-sky-100 text-sky-800',       'bg' => '#E0F2FE', 'text' => '#075985'],
        'purple' => ['tw' => 'bg-purple-100 text-purple-800', 'bg' => '#F3E8FF', 'text' => '#6B21A8'],
        'pink'   => ['tw' => 'bg-pink-100 text-pink-800',     'bg' => '#FCE7F3', 'text' => '#9D174D'],
        'teal'   => ['tw' => 'bg-teal-100 text-teal-800',     'bg' => '#CCFBF1', 'text' => '#115E59'],
        'gray'   => ['tw' => 'bg-gray-100 text-gray-600',     'bg' => '#F3F4F6', 'text' => '#4B5563'],
    ];

    /**
     * Default legend seeded per company on first use — mirrors the manual
     * attendance summary sheet this module replaces, with the house
     * convention: tick = Present, X = Day Off, ABS = Absent.
     */
    public const DEFAULTS = [
        ['✓',   'Present',                                  'green',  'present'],
        ['X',   'Day Off',                                  'slate',  'off'],
        ['ABS', 'Absent',                                   'red',    'absent'],
        ['AL',  'Annual Leave',                             'amber',  null],
        ['SL',  'Sick Leave',                               'yellow', null],
        ['EL',  'Emergency Leave',                          'orange', null],
        ['RPH', 'Public Holiday',                           'purple', null],
        ['CF',  'Carry Forward',                            'yellow', null],
        ['UPL', 'Unpaid Leave',                             'red',    null],
        ['HD',  'Half Day',                                 'sky',    null],
        ['HL',  'Hospitalisation Leave',                    'pink',   null],
        ['MR',  'Marriage Leave',                           'pink',   null],
        ['MAT', 'Maternity Leave',                          'pink',   null],
        ['PL',  'Paternity Leave',                          'blue',   null],
        ['CLD', 'CL: Death Of Immediate Family',            'gray',   null],
        ['CLH', 'CL: Critical Illness Of Immediate Family', 'gray',   null],
        ['CLN', 'CL: Natural Disaster',                     'gray',   null],
        ['RO',  'Replacement Off Day',                      'teal',   null],
        ['RL',  'Replacement Leave',                        'teal',   null],
        ['OS',  'Out Station',                              'blue',   null],
        ['TR',  'Training',                                 'sky',    null],
        ['MTG', 'Meeting',                                  'sky',    null],
        ['EXL', 'Examination Leave',                        'purple', null],
        ['CH',  'Claim Hours',                              'teal',   null],
        ['UNR', 'Unrecorded',                               'gray',   null],
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Seed the default legend for a company that has no codes yet. */
    public static function seedDefaults(int $companyId): void
    {
        if (static::where('company_id', $companyId)->exists()) {
            return;
        }
        foreach (static::DEFAULTS as $i => [$code, $label, $color, $systemKey]) {
            static::create([
                'company_id' => $companyId,
                'code'       => $code,
                'label'      => $label,
                'color'      => $color,
                'system_key' => $systemKey,
                'sort_order' => ($i + 1) * 10,
                'is_active'  => true,
            ]);
        }
    }

    /** Tailwind classes / hex pair for this code's color (slate fallback). */
    public function colorMeta(): array
    {
        return static::COLORS[$this->color] ?? static::COLORS['slate'];
    }
}
