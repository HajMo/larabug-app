<?php

namespace App\Models;

use Kblais\Uuid\Uuid;
use EloquentFilter\Filterable;
use Illuminate\Database\Eloquent\Model;
use App\Notifications\ProjectWasCreated;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property string slack_webhook
 * @property string discord_channel
 * @property string discord_webhook
 * @property string title
 * @property mixed pivot
 * @property boolean receive_email
 * @property boolean notifications_enabled
 * @property boolean mobile_notifications_enabled
 * @property boolean slack_webhook_enabled
 * @property boolean discord_webhook_enabled
 * @property boolean custom_webhook_enabled
 * @property string key
 * @property mixed url
 */
class Project extends Model
{
    use Uuid,
        Filterable,
        Notifiable,
        HasFactory;

    protected $fillable = [
        'url',
        'title',
        'description',
        'receive_email',
        'notifications_enabled',
        'slack_webhook',
        'discord_webhook',
        'custom_webhook',
        'mobile_notifications_enabled',
        'slack_webhook_enabled',
        'discord_webhook_enabled',
        'custom_webhook_enabled',
    ];

    protected $dates = [
        'last_exception_at',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'receive_email' => 'boolean',
        'notifications_enabled' => 'boolean',
        'mobile_notifications_enabled' => 'boolean',
        'slack_webhook_enabled' => 'boolean',
        'discord_webhook_enabled' => 'boolean',
        'custom_webhook_enabled' => 'boolean',
    ];

    protected $appends = [
        'route_url',
        'feedback_script_html'
    ];

    public function getRouteUrlAttribute()
    {
        return route('panel.projects.show', $this);
    }

    public function getFeedbackScriptUrl()
    {
        return route('feedback.script', ['project' => $this->id]);
    }

    public function getFeedbackScriptHtmlAttribute()
    {
        return '<script src="' . $this->getFeedbackScriptUrl() . '"></script>';
    }

    public function routeNotificationForSlack()
    {
        return $this->slack_webhook;
    }

    public function routeNotificationForDiscord()
    {
        return $this->discord_webhook;
    }

    public function routeNotificationForWebhook()
    {
        return $this->custom_webhook;
    }

    public function isOwner()
    {
        return $this->pivot->owner;
    }

    public function hasNotificationChannelsEnabled()
    {
        return $this->slack_webhook || $this->discord_webhook;
    }

    public function scopeWantsEmail($query)
    {
        return $query->where('receive_email', true);
    }

    public function users()
    {
        return $this->belongsToMany(\App\Models\User::class)->withPivot('owner');
    }

    public function group()
    {
        return $this->belongsTo(\App\Models\ProjectGroup::class);
    }

    public function exceptions()
    {
        return $this->hasMany(\App\Models\Exception::class);
    }

    public function unreadExceptions()
    {
        return $this->exceptions()
            ->where(function ($query) {
                return $query
                    ->where('status', \App\Models\Exception::OPEN);
            });
    }

    public function feedback()
    {
        return $this->hasManyThrough(Feedback::class, Exception::class);
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['search'] ?? null, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%');
            });
        });
    }

    public function routeNotificationForFcm()
    {
        return $this->users()->wherePivot('owner', true)->first()
            ->fcmTokens()
            ->get()
            ->map(function ($fcmToken) {
                return $fcmToken->token;
            })->toArray();
    }

    public static function boot()
    {
        parent::boot();

        static::creating(function (self $project) {
            $project->key = str_random(50);
        });

        static::created(function (self $project) {
            if (auth()->check()) {
                auth()->user()->notify(new ProjectWasCreated($project));
            }
        });

        static::deleting(function (self $project) {
            $project->exceptions()->delete();
            $project->feedback()->delete();
        });
    }
}
