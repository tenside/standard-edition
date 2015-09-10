<?php

/**
 * This file is part of tenside/standard-edition.
 *
 * (c) Christian Schiffler <c.schiffler@cyberspectrum.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    tenside/standard-edition
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  2015 Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @license    https://github.com/tenside/standard-edition/blob/master/LICENSE MIT
 * @link       https://github.com/tenside/standard-edition
 * @filesource
 */

namespace Tenside\StandardEdition;

use Tenside\CoreBundle\TensideJsonConfig;

/**
 * Signs URIs.
 *
 * This service behaves exactly like the one in the symfony framework but is ready for phar use.
 * As the kernel.secret is not available during phar compile time, we need it lazy loaded.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class UriSigner
{
    /**
     * The resolved secret.
     *
     * @var string
     */
    private $secret;

    /**
     * The name of the secret parameter in the kernel.
     *
     * @var TensideJsonConfig
     */
    private $tensideConfig;

    /**
     * Constructor.
     *
     * @param TensideJsonConfig $tensideConfig The tenside config.
     */
    public function __construct(TensideJsonConfig $tensideConfig)
    {
        $this->tensideConfig = $tensideConfig;
    }

    /**
     * Signs a URI.
     *
     * The given URI is signed by adding a _hash query string parameter
     * which value depends on the URI and the secret.
     *
     * @param string $uri A URI to sign.
     *
     * @return string The signed URI
     */
    public function sign($uri)
    {
        $url = parse_url($uri);
        if (isset($url['query'])) {
            parse_str($url['query'], $params);
        } else {
            $params = array();
        }

        $uri = $this->buildUrl($url, $params);

        return $uri.(false === (strpos($uri, '?')) ? '?' : '&').'_hash='.$this->computeHash($uri);
    }

    /**
     * Checks that a URI contains the correct hash.
     *
     * The _hash query string parameter must be the last one
     * (as it is generated that way by the sign() method, it should
     * never be a problem).
     *
     * @param string $uri A signed URI.
     *
     * @return bool True if the URI is signed correctly, false otherwise
     */
    public function check($uri)
    {
        $url = parse_url($uri);
        if (isset($url['query'])) {
            parse_str($url['query'], $params);
        } else {
            $params = array();
        }

        if (empty($params['_hash'])) {
            return false;
        }

        $hash = urlencode($params['_hash']);
        unset($params['_hash']);

        return $this->computeHash($this->buildUrl($url, $params)) === $hash;
    }

    /**
     * Calculate the hash.
     *
     * @param string $uri The uri to calculate the hash for.
     *
     * @return string
     */
    private function computeHash($uri)
    {
        if (!isset($this->secret)) {
            $this->secret = $this->tensideConfig->getSecret();
        }

        return urlencode(base64_encode(hash_hmac('sha256', $uri, $this->secret, true)));
    }

    /**
     * Build the url.
     *
     * @param array $url    The url values.
     *
     * @param array $params The url parameters.
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function buildUrl(array $url, array $params = array())
    {
        ksort($params);
        $url['query'] = http_build_query($params, '', '&');

        $scheme   = isset($url['scheme']) ? $url['scheme'].'://' : '';
        $host     = isset($url['host']) ? $url['host'] : '';
        $port     = isset($url['port']) ? ':'.$url['port'] : '';
        $user     = isset($url['user']) ? $url['user'] : '';
        $pass     = isset($url['pass']) ? ':'.$url['pass']  : '';
        $pass     = ($user || $pass) ? $pass . '@' : '';
        $path     = isset($url['path']) ? $url['path'] : '';
        $query    = isset($url['query']) && $url['query'] ? '?'.$url['query'] : '';
        $fragment = isset($url['fragment']) ? '#'.$url['fragment'] : '';

        return $scheme.$user.$pass.$host.$port.$path.$query.$fragment;
    }
}
