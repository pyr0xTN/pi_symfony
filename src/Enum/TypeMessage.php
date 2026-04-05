<?php

namespace App\Enum;

enum TypeMessage:string
{
    case TEXTE = 'TEXTE';
    case IMAGE = 'IMAGE';
    case FICHIER = 'FICHIER';
    case LOCATION = 'LOCATION';
    case AUDIO = 'AUDIO';
}