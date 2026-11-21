<?php

/**
 * -------------------------------------------------------------------------
 * FinOps plugin for GLPI
 * Copyright (C) 2026 Vincent GUILLOTTE
 * https://github.com/parime/glpi-finops
 * -------------------------------------------------------------------------
 * LICENSE
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version. See LICENSE for the full text.
 * -------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace GlpiPlugin\Finops\Ovh;

/**
 * Lecture OVHcloud en échec. Le motif (constantes R_*) donne le message traduit affiché ; le
 * détail technique ne contient jamais les clés ni le texte renvoyé par OVHcloud.
 */
final class OvhException extends \RuntimeException
{
    public const R_NETWORK = 'network';
    public const R_AUTH = 'auth';
    public const R_NOT_FOUND = 'not_found';
    public const R_RATE = 'rate';
    public const R_SERVER = 'server';
    public const R_RESPONSE = 'response';
    public const R_TOO_MUCH = 'too_much';
    public const R_KEYS = 'keys';

    public function __construct(public readonly string $reason, string $detail = '')
    {
        parent::__construct($detail === '' ? $reason : $reason . ': ' . $detail);
    }

    /** Message traduit, sans donnée venue d'OVHcloud. */
    public function label(): string
    {
        return match ($this->reason) {
            self::R_NETWORK   => __('OVHcloud cannot be reached (network, proxy or firewall).', 'finops'),
            self::R_AUTH      => __(
                'OVHcloud refused the keys: check them and the rights of the consumer key.',
                'finops'
            ),
            self::R_NOT_FOUND => __(
                'OVHcloud does not know an item asked for: check the project identifiers.',
                'finops'
            ),
            self::R_RATE      => __('OVHcloud limits the number of calls: try again in a few minutes.', 'finops'),
            self::R_SERVER    => __('OVHcloud is unavailable: try again later.', 'finops'),
            self::R_TOO_MUCH  => __('Too much data to read at once: reduce the number of months.', 'finops'),
            self::R_KEYS      => __('The keys of this account are incomplete.', 'finops'),
            default           => __('OVHcloud sent an unexpected answer.', 'finops'),
        };
    }
}
