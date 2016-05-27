<?php

namespace App;

use App\Support\IP;
use Illuminate\Database\Eloquent\Model;

class BoardAdventure extends Model
{
    use \App\Traits\EloquentBinary;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'board_adventures';

    /**
     * The database primary key.
     *
     * @var string
     */
    protected $primaryKey = 'adventure_id';

    /**
     * Attributes which are automatically sent through a Carbon instance on load.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'expires_at', 'expended_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['adventurer_ip', 'board_uri', 'expires_at'];

    public function board()
    {
        return $this->belongsTo('\App\Board', 'board_uri');
    }

    /**
     * Gets our binary value and unwraps it from any stream wrappers.
     *
     * @param mixed $value
     *
     * @return IP
     */
    public function getAdventurerIpAttribute($value)
    {
        return new IP($value);
    }

    /**
     * Sets our binary value and encodes it if required.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function setAdventurerIpAttribute($value)
    {
        $this->attributes['adventurer_ip'] = (new IP($value))->toSQL();
    }

    public function scopeWhereBoard($query, Board $board)
    {
        return $query->where('board_uri', $board->board_uri);
    }

    public function scopeWhereBelongsToClient($query)
    {
        return $query->where('adventurer_ip', (new IP())->toSQL());
    }

    public function scopeWhereFresh($query)
    {
        return $query->where('expires_at', '>=', $this->freshTimestamp())
            ->whereNull('expended_at');
    }

    public function scopeWhereExpended($query)
    {
        return $query->where('expended_at', '>', 0);
    }

    public function scopeWhereExpired($query)
    {
        return $query->where('expires_at', '<', $this->freshTimestamp());
    }

    public static function getAdventure(Board $board)
    {
        $adventures = static::whereFresh()
            ->whereBoard($board)
            ->whereBelongsToClient()
            ->get();

        if (count($adventures)) {
            return $adventures->first();
        }

        return;
    }
}
