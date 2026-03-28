<?php

declare(strict_types=1);

namespace App\Enums;

enum OauthProvider: string
{
    case Google = 'google';
    case Vkontakte = 'vk';
    case Instagram = 'instagram';
    case Facebook = 'facebook';
}
