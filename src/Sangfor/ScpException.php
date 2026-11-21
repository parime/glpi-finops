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

namespace GlpiPlugin\Finops\Sangfor;

/**
 * Lecture Sangfor SCP en échec. Le motif (constantes R_*) donne le message traduit affiché ; le
 * détail technique ne contient jamais l'identifiant, le mot de passe ni le texte renvoyé par SCP.
 */
final class ScpException extends \RuntimeException
{
    public const R_NETWORK = 'network';
    public const R_TLS = 'tls';
    public const R_AUTH = 'auth';
    public const R_NOT_FOUND = 'not_found';
    public const R_RATE = 'rate';
    public const R_SERVER = 'server';
    public const R_RESPONSE = 'response';
    public const R_TOO_MUCH = 'too_much';
    public const R_CONFIG = 'config';
    public const R_CRYPTO = 'crypto';

    public function __construct(public readonly string $reason, string $detail = '')
    {
        parent::__construct($detail === '' ? $reason : $reason . ': ' . $detail);
    }

    /** Message traduit, sans donnée venue de SCP. */
    public function label(): string
    {
        return match ($this->reason) {
            self::R_NETWORK   => __('Sangfor SCP cannot be reached (address, network, proxy or firewall).', 'finops'),
            self::R_TLS       => __(
                'The certificate of Sangfor SCP is not trusted: install its authority on the GLPI server, '
                . 'or allow self-signed certificates for this platform.',
                'finops'
            ),
            self::R_AUTH      => __(
                'Sangfor SCP refused the login: check the user name, the password and the rights of the account.',
                'finops'
            ),
            self::R_NOT_FOUND => __(
                'This address does not answer like Sangfor SCP 6.3.0 or later (OpenAPI not found).',
                'finops'
            ),
            self::R_RATE      => __('Sangfor SCP limits the number of calls: try again in a few minutes.', 'finops'),
            self::R_SERVER    => __('Sangfor SCP is unavailable: try again later.', 'finops'),
            self::R_TOO_MUCH  => __('Too many virtual machines to read at once.', 'finops'),
            self::R_CONFIG    => __('The address or the login of this platform is incomplete.', 'finops'),
            self::R_CRYPTO    => __(
                'The password cannot be encrypted for Sangfor SCP (OpenSSL extension of PHP missing '
                . 'or public key not recognized).',
                'finops'
            ),
            default           => __('Sangfor SCP sent an unexpected answer.', 'finops'),
        };
    }
}
