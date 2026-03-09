<?php

namespace App\Models;

use App\Scopes\CompanyScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAnalysisLog extends Model
{
    protected $fillable = [
        'company_id', 'outlet_id', 'period', 'analysis_type',
        'prompt_hash', 'prompt_text', 'response_text', 'model_used',
        'input_tokens', 'output_tokens', 'requested_by',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope());
    }

    public function company(): BelongsTo    { return $this->belongsTo(Company::class); }
    public function outlet(): BelongsTo     { return $this->belongsTo(Outlet::class); }
    public function requestedBy(): BelongsTo { return $this->belongsTo(User::class, 'requested_by'); }
}
