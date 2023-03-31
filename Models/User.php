<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;

use Vizir\KeycloakWebGuard\Models\KeycloakUser;

/**
 * Class User
 * @package App\Models
 * @property string[] $groups
 */
class User extends KeycloakUser
{
    use Notifiable;
    protected $fillable = [
        'name', 'email', 'sub', 'groups'
    ];

    public function getKey()
    {
        return $this->attributes['sub'];
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->attributes;
    }

    /**
     * @param string $find
     * @return bool
     */
    public function hasGroup(string $find): bool
    {
        if(count($this->groups) > 0) {
            foreach ($this->groups as $group) {
                if(str_contains($group, $find)){
                    return true;
                }
            }
        }
        return false;
    }
}
