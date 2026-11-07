<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipTransaction extends Model
{
    protected $fillable = ['user_id','invoice_id','actor_id','points','balance_after','description'];
    protected function casts(): array
    {
        return ['points'=>'integer','balance_after'=>'integer'];
    }
    public function user() { return $this->belongsTo(User::class)->withTrashed(); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id')->withTrashed(); }
    public function invoice() { return $this->belongsTo(Invoice::class); }
}
