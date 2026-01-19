<?php

namespace Stumason\Coolify\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string $name
 * @property string $server_uuid
 * @property string $project_uuid
 * @property string $environment
 * @property string|null $deploy_key_uuid
 * @property string|null $repository
 * @property string|null $branch
 * @property string|null $application_uuid
 * @property string|null $database_uuid
 * @property string|null $redis_uuid
 * @property bool $is_default
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static> where($column, $operator = null, $value = null, $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder<static> query()
 * @method static static updateOrCreate(array $attributes, array $values = [])
 */
class CoolifyResource extends Model
{
    protected $fillable = [
        'name',
        'server_uuid',
        'project_uuid',
        'environment',
        'deploy_key_uuid',
        'repository',
        'branch',
        'application_uuid',
        'database_uuid',
        'redis_uuid',
        'is_default',
        'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the default resource configuration.
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }

    /**
     * Set this resource as the default, unsetting any other default.
     */
    public function setAsDefault(): self
    {
        return DB::transaction(function () {
            static::where('is_default', true)->update(['is_default' => false]);
            $this->update(['is_default' => true]);

            return $this;
        });
    }
}
