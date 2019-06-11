<?php declare(strict_types=1);

namespace VladFlonta\WebApiLog\Logger;

use Exception;

class Php2Curl
{
    const OPEN_BRACE    = '[';
    const CLOSING_BRACE = ']';
    const DOUBLE_QOUTE  = '"';
    const QOUTE         = "'";
    // this is how we are going to escape a single quote in post parameters
    const QOUTE_REPLACEMENT = "'\\''";

    /** @var array */
    private $get;

    /** @var array */
    private $post;

    /** @var array */
    private $request;

    /** @var array */
    private $server;

    /** @var array */
    private $headers;

    /** @var string */
    private $phpInput;

    private $guessedContentType;

    // not the full list, just special cases from Postman app
    const CONTENT_TYPE_FORM_DATA        = 'multipart/form-data';
    const CONTENT_TYPE_FORM_URL_ENCODED = 'x-www-form-urlencoded';
    const CONTENT_TYPE_UNKNOWN          = 'unknown';

    public function __construct($get = null, $post = null, $request = null, $server = null, $headers = null, $phpInput = null)
    {
        $this->get     = $get ?? $_GET;
        $this->post    = $post ?? $_POST;
        $this->request = $request ?? $_REQUEST;
        $this->server  = $server ?? $_SERVER;
        $this->headers = $headers ?? getallheaders();

        $this->phpInput = $phpInput ?? file_get_contents('php://input');

        // i have heard complicated actions inside constructor is anti-pattern, but with this comment it is not anymore (it is actually "tech debt")
        $this->guessedContentType = $this->guessContentTypeFromHeaders();
        $this->removeBoundaryPartFromContentType();
        $this->removeContentLengthFromHeaders();
        $this->injectCustomUserAgent();
    }

    private function injectCustomUserAgent()
    {
        $this->eliminateKeyFromServerAndHeaders('user-agent');
        $ua                              = 'php2curl Agent - github.com/biganfa/php2curl';
        $this->server['HTTP_USER_AGENT'] = $ua;
        $this->headers['User-Agent']     = $ua;
    }

    private function removeContentLengthFromHeaders()
    {
        $this->eliminateKeyFromServerAndHeaders('content-length');
    }

    private function removeBoundaryPartFromContentType()
    {
        // in RFC it is said that boundary is required. In practice, everything works like a charm without boundary part. Maybe it could be a problem for file uploads? will fix in v2.
        // also we want to drop the 'content-length' header, because if we remove the boundary, the body is changed and request just hangs forever
        $purgeBoundaryPartLambda = function (&$headerValue, $headerName) {
            $headerName = strtolower($headerName);
            if ($headerName === 'content-type' || $headerName === 'http-content-type')
            {
                $headerValue = preg_replace('/; boundary=(-)+[[:digit:]]+$/', '', $headerValue);
            }
        };
        array_walk($this->server, $purgeBoundaryPartLambda);
        array_walk($this->headers, $purgeBoundaryPartLambda);
    }

    private function eliminateKeyFromServerAndHeaders($targetKey)
    {
        $targetKey          = strtolower($targetKey);
        $arrayFilterClosure = function ($key) use ($targetKey) {
            return ! (strtolower($key) == $targetKey || strtolower($key) == $targetKey);
        };
        $this->server       = array_filter($this->server, $arrayFilterClosure, ARRAY_FILTER_USE_KEY);
        $this->headers      = array_filter($this->headers, $arrayFilterClosure, ARRAY_FILTER_USE_KEY);
    }

    private function guessContentTypeFromHeaders()
    {
        foreach ($this->getHeadersArray() as $header => $value)
        {

            if (strtolower($header) == 'content-type')
            {

                if (stripos($value, 'multipart/form-data') !== false)
                {
                    return self::CONTENT_TYPE_FORM_DATA;
                }

                if (stripos($value, 'www-form-urlencoded') !== false)
                {
                    return self::CONTENT_TYPE_FORM_URL_ENCODED;
                }

                return self::CONTENT_TYPE_UNKNOWN;
            }
        }

        return self::CONTENT_TYPE_UNKNOWN;
    }

    /**
     * @return string
     * @throws Exception
     */
    public function doAll()
    {
        return "curl --insecure "
            . '-X ' . $this->getMethod()
            . ' ' . self::DOUBLE_QOUTE . $this->getFullURLPart() . self::DOUBLE_QOUTE
            . $this->getHeadersPart()
            . $this->getRequestBodyPart();
    }

    private function getMethod()
    {
        return $this->server['REQUEST_METHOD'];
    }

    private function getFullURLPart()
    {

        $portPart = '';
        if (isset($this->server['SERVER_PORT']) && $this->server['SERVER_PORT'] != '80')
        {
            $portPart = ':' . $this->server['SERVER_PORT'];
        }

        return $this->server['SERVER_NAME'] . $portPart . $this->server['REQUEST_URI'];
    }

    private function getHeadersPart()
    {
        $result = '';

        foreach ($this->getHeadersArray() as $key => $value)
        {

            $result .= " -H '$key: $value'";
        }

        return $result;
    }

    private function escapeSingleQuote($parameter)
    {
        return str_replace(self::QOUTE, self::QOUTE_REPLACEMENT, $parameter);
    }

    /**
     * @return string
     * @throws Exception
     */
    private function getRequestBodyPart()
    {

        switch ($this->getMethod())
        {

            case 'POST':
            case 'PUT':
            case 'PATCH':
            case 'DELETE':

                if ($this->post || $this->phpInput)
                {

                    switch ($this->guessedContentType)
                    {
                        case self::CONTENT_TYPE_FORM_DATA: // RFC 2388

                            $paramsArray = [];
                            foreach ($this->post as $key => $value)
                            {
                                if (is_array($value))
                                {
                                    foreach ($value as $subKey => $subValue)
                                    {
                                        if (is_array($subValue))
                                        {
                                            throw new Exception("2-dimensional arrays are not supported");
                                        }

                                        $subValue      = $this->escapeSingleQuote($subValue);
                                        $paramsArray[] = "$key" . $this::OPEN_BRACE . $subKey . $this::CLOSING_BRACE . '=' . $subValue;
                                    }
                                } else
                                {
                                    $value         = $this->escapeSingleQuote($value);
                                    $paramsArray[] = "$key=$value";
                                }
                            }

                            $imploadedParams = implode("' --form '", $paramsArray);

                            return " --form '$imploadedParams'";

                            break;

                        case self::CONTENT_TYPE_FORM_URL_ENCODED:

                            $data = http_build_query($this->post, '', '&', PHP_QUERY_RFC3986);

                            return " --data '$data'";

                            break;

                        case self::CONTENT_TYPE_UNKNOWN: // includes application/json, etc.
                            if ($this->phpInput)
                            {
                                $body = $this->escapeSingleQuote($this->phpInput);

                                return " --data '$body'";
                            }

                            break;
                    }
                }

                break;

            case 'OPTIONS':
                // does anyone send request body in case OPTIONS ?
                return '';
                break;
        };

        return '';
    }

    private function getHeadersArray()
    {
        return $this->headers;
    }
}
