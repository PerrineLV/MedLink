<?php

declare(strict_types=1);

namespace App\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * ML-93 : en prod (conteneur stateless, image buildée en root, process
 * PHP-FPM en www-data), un handler Monolog de type "stream" pointant vers un
 * chemin fichier sous var/log ne peut pas créer ce répertoire au runtime
 * ("Permission denied") — c'est ce qui a cassé le canal security_audit.
 * Les handlers main/deprecation écrivent déjà sur php://stderr pour cette
 * raison ; security_audit doit suivre le même pattern.
 */
final class MonologProdConfigTest extends TestCase
{
    public function testSecurityAuditHandlerWritesToStderrInProd(): void
    {
        $config = Yaml::parseFile(__DIR__.'/../../config/packages/monolog.yaml');

        $prodHandlers = $config['when@prod']['monolog']['handlers'];

        self::assertSame(
            'php://stderr',
            $prodHandlers['security_audit']['path'],
            'Le handler security_audit doit écrire sur php://stderr en prod, comme main/deprecation, '.
            'car var/log n\'existe pas et ne peut pas être créé par le process PHP-FPM (www-data) '.
            'dans le conteneur de prod (ML-93).',
        );
    }
}
