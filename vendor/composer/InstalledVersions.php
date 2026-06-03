<?php











namespace Composer;

use Composer\Semver\VersionParser;






class InstalledVersions
{
private static $installed = array (
  'root' => 
  array (
    'pretty_version' => '1.0.0+no-version-set',
    'version' => '1.0.0.0',
    'aliases' => 
    array (
    ),
    'reference' => NULL,
    'name' => '__root__',
  ),
  'versions' => 
  array (
    '__root__' => 
    array (
      'pretty_version' => '1.0.0+no-version-set',
      'version' => '1.0.0.0',
      'aliases' => 
      array (
      ),
      'reference' => NULL,
    ),
    'firebase/php-jwt' => 
    array (
      'pretty_version' => 'v7.0.5',
      'version' => '7.0.5.0',
      'aliases' => 
      array (
      ),
      'reference' => '47ad26bab5e7c70ae8a6f08ed25ff83631121380',
    ),
    'google/apiclient' => 
    array (
      'pretty_version' => 'v2.19.3',
      'version' => '2.19.3.0',
      'aliases' => 
      array (
      ),
      'reference' => 'a1f02761994fd9defb20f6f1449205fd66f450de',
    ),
    'google/apiclient-services' => 
    array (
      'pretty_version' => 'v0.441.0',
      'version' => '0.441.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '9e3c10389ed39915261e5bc98a687c2b1307040d',
    ),
    'google/auth' => 
    array (
      'pretty_version' => 'v1.50.1',
      'version' => '1.50.1.0',
      'aliases' => 
      array (
      ),
      'reference' => '870c17ee3a1d73338d39a9ffa77a700ba77f5a83',
    ),
    'guzzlehttp/guzzle' => 
    array (
      'pretty_version' => '7.10.0',
      'version' => '7.10.0.0',
      'aliases' => 
      array (
      ),
      'reference' => 'b51ac707cfa420b7bfd4e4d5e510ba8008e822b4',
    ),
    'guzzlehttp/promises' => 
    array (
      'pretty_version' => '2.3.0',
      'version' => '2.3.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '481557b130ef3790cf82b713667b43030dc9c957',
    ),
    'guzzlehttp/psr7' => 
    array (
      'pretty_version' => '2.9.0',
      'version' => '2.9.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '7d0ed42f28e42d61352a7a79de682e5e67fec884',
    ),
    'monolog/monolog' => 
    array (
      'pretty_version' => '3.10.0',
      'version' => '3.10.0.0',
      'aliases' => 
      array (
      ),
      'reference' => 'b321dd6749f0bf7189444158a3ce785cc16d69b0',
    ),
    'psr/cache' => 
    array (
      'pretty_version' => '3.0.0',
      'version' => '3.0.0.0',
      'aliases' => 
      array (
      ),
      'reference' => 'aa5030cfa5405eccfdcb1083ce040c2cb8d253bf',
    ),
    'psr/http-client' => 
    array (
      'pretty_version' => '1.0.3',
      'version' => '1.0.3.0',
      'aliases' => 
      array (
      ),
      'reference' => 'bb5906edc1c324c9a05aa0873d40117941e5fa90',
    ),
    'psr/http-client-implementation' => 
    array (
      'provided' => 
      array (
        0 => '1.0',
      ),
    ),
    'psr/http-factory' => 
    array (
      'pretty_version' => '1.1.0',
      'version' => '1.1.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '2b4765fddfe3b508ac62f829e852b1501d3f6e8a',
    ),
    'psr/http-factory-implementation' => 
    array (
      'provided' => 
      array (
        0 => '1.0',
      ),
    ),
    'psr/http-message' => 
    array (
      'pretty_version' => '2.0',
      'version' => '2.0.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '402d35bcb92c70c026d1a6a9883f06b2ead23d71',
    ),
    'psr/http-message-implementation' => 
    array (
      'provided' => 
      array (
        0 => '1.0',
      ),
    ),
    'psr/log' => 
    array (
      'pretty_version' => '3.0.2',
      'version' => '3.0.2.0',
      'aliases' => 
      array (
      ),
      'reference' => 'f16e1d5863e37f8d8c2a01719f5b34baa2b714d3',
    ),
    'psr/log-implementation' => 
    array (
      'provided' => 
      array (
        0 => '3.0.0',
      ),
    ),
    'ralouphie/getallheaders' => 
    array (
      'pretty_version' => '3.0.3',
      'version' => '3.0.3.0',
      'aliases' => 
      array (
      ),
      'reference' => '120b605dfeb996808c31b6477290a714d356e822',
    ),
    'symfony/deprecation-contracts' => 
    array (
      'pretty_version' => 'v3.7.0',
      'version' => '3.7.0.0',
      'aliases' => 
      array (
      ),
      'reference' => '50f59d1f3ca46d41ac911f97a78626b6756af35b',
    ),
  ),
);







public static function getInstalledPackages()
{
return array_keys(self::$installed['versions']);
}









public static function isInstalled($packageName)
{
return isset(self::$installed['versions'][$packageName]);
}














public static function satisfies(VersionParser $parser, $packageName, $constraint)
{
$constraint = $parser->parseConstraints($constraint);
$provided = $parser->parseConstraints(self::getVersionRanges($packageName));

return $provided->matches($constraint);
}










public static function getVersionRanges($packageName)
{
if (!isset(self::$installed['versions'][$packageName])) {
throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}

$ranges = array();
if (isset(self::$installed['versions'][$packageName]['pretty_version'])) {
$ranges[] = self::$installed['versions'][$packageName]['pretty_version'];
}
if (array_key_exists('aliases', self::$installed['versions'][$packageName])) {
$ranges = array_merge($ranges, self::$installed['versions'][$packageName]['aliases']);
}
if (array_key_exists('replaced', self::$installed['versions'][$packageName])) {
$ranges = array_merge($ranges, self::$installed['versions'][$packageName]['replaced']);
}
if (array_key_exists('provided', self::$installed['versions'][$packageName])) {
$ranges = array_merge($ranges, self::$installed['versions'][$packageName]['provided']);
}

return implode(' || ', $ranges);
}





public static function getVersion($packageName)
{
if (!isset(self::$installed['versions'][$packageName])) {
throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}

if (!isset(self::$installed['versions'][$packageName]['version'])) {
return null;
}

return self::$installed['versions'][$packageName]['version'];
}





public static function getPrettyVersion($packageName)
{
if (!isset(self::$installed['versions'][$packageName])) {
throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}

if (!isset(self::$installed['versions'][$packageName]['pretty_version'])) {
return null;
}

return self::$installed['versions'][$packageName]['pretty_version'];
}





public static function getReference($packageName)
{
if (!isset(self::$installed['versions'][$packageName])) {
throw new \OutOfBoundsException('Package "' . $packageName . '" is not installed');
}

if (!isset(self::$installed['versions'][$packageName]['reference'])) {
return null;
}

return self::$installed['versions'][$packageName]['reference'];
}





public static function getRootPackage()
{
return self::$installed['root'];
}







public static function getRawData()
{
return self::$installed;
}



















public static function reload($data)
{
self::$installed = $data;
}
}
