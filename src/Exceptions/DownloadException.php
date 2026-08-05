<?php

declare(strict_types=1);

namespace Moudarir\Downloader\Exceptions;

use Exception;

class DownloadException extends Exception
{

    public static function filepathNotFound(): static
    {
        return new static("Le chemin du fichier indiqué est introuvable.");
    }

    public static function filesizeIssue(): static
    {
        return new static("Erreur lors de la récupération de la taille du fichier.");
    }

    public static function invalidHeaderName(string $name): static
    {
        return new static("Le nom de l'entête `$name` est incorrect.");
    }

    public static function eTagStrategyFailed(string $name): static
    {
        return new static("Le calcul de l'eTag avec la stratégie `$name` a échoué.");
    }

    public static function emptyDataSource(): static
    {
        return new static("La source de donnés est vide.");
    }

    public static function filenameRequiredForData(): static
    {
        return new static("Le nom du fichier est obligatoire pour le téléchargement de données.");
    }

    public static function operationNotSupportedOnData(string $operation): static
    {
        return new static("L'opération `$operation` n'est pas supportée lors de la diffusion de données en mémoire.");
    }

    public static function noETagStrategySupported(string $resource): static
    {
        return new static("La ressource `$resource` ne déclare aucun `ETag strategy` supporté.");
    }
}
