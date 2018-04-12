<?php
/**
 * @package     VladFlonta\WebApiLog
 * @author      Vlad Flonta
 * @copyright   Copyright © 2018
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace VladFlonta\WebApiLog\Logger\Handler;

class Debug extends \Magento\Framework\Logger\Handler\Debug
{
    /** @var string */
    private $errorMessage;

    /** @var boolean */
    private $dirCreated;

    /**
     * @param array $record
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function write(array $record)
    {
        if (!isset($record['context']['is_api']) || !$record['context']['is_api']) {
            parent::write($record);
            return;
        }
        $result = preg_match('/\/V1\/([^?]*)/', $record['context']['request']['uri'], $matches);
        $url = sprintf(
            '%s/var/log/webapi_%s/%s/%s.log',
            BP,
            'rest',
            $result && count($matches) && $matches[1] ? trim($matches[1], '/') : 'default',
            $record['datetime']->format('Ymd_His')
        );

        $logDir = $this->filesystem->getParentDirectory($url);
        if (!$this->filesystem->isDirectory($logDir)) {
            $this->filesystem->createDirectory($logDir);
        }

        $stream = null;

        if (!is_resource($stream)) {
            if (!$url) {
                throw new \LogicException('Missing stream url, the stream can not be opened.');
            }
            $this->createDir($url);
            $this->errorMessage = null;
            set_error_handler(array($this, 'customErrorHandler'));
            $stream = fopen($url, 'a');
            if ($this->filePermission !== null) {
                @chmod($url, $this->filePermission);
            }
            restore_error_handler();
            if (!is_resource($stream)) {
                $stream = null;
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('The stream or file "%1" could not be opened: %2', $url, $this->errorMessage)
                );
            }
        }

        if ($this->useLocking) {
            flock($stream, LOCK_EX);
        }

        $request = $record['context']['request'];
        $data = '';
        $data .= sprintf("%s %s HTTP %s\n\n", $request['method'], $request['uri'], $request['version']);
        foreach ($record['context']['request']['headers'] as $key => $value) {
            $data .= sprintf("%s: %s\n", $key, $value);
        }
        $data .= sprintf("\n%s\n\n", $request['body']);
        foreach ($record['context']['response']['headers'] as $key => $value) {
            $data .= sprintf("%s: %s\n", $key, $value);
        }
        $data .= sprintf("\n%s\n", $record['context']['response']['body']);

        fwrite($stream, $data);

        if ($this->useLocking) {
            flock($stream, LOCK_UN);
        }

        if (is_resource($stream)) {
            fclose($stream);
        }
        $stream = null;
    }

    /**
     * @param $code
     * @param $msg
     */
    private function customErrorHandler($code, $msg)
    {
        $this->errorMessage = preg_replace('{^(fopen|mkdir)\(.*?\): }', '', $msg);
    }

    /**
     * @param string $stream
     *
     * @return null|string
     */
    private function getDirFromStream($stream)
    {
        $pos = strpos($stream, '://');
        if ($pos === false) {
            return dirname($stream);
        }

        if ('file://' === substr($stream, 0, 7)) {
            return dirname(substr($stream, 7));
        }

        return;
    }

    /**
     * @param $url
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    private function createDir($url)
    {
        if ($this->dirCreated) {
            return;
        }

        $dir = $this->getDirFromStream($url);
        if (null !== $dir && !is_dir($dir)) {
            $this->errorMessage = null;
            set_error_handler(array($this, 'customErrorHandler'));
            $status = mkdir($dir, 0777, true);
            restore_error_handler();
            if (false === $status) {
                throw new \Magento\Framework\Exception\LocalizedException(
                    __('There is no existing directory at "%1" and its not buildable: %2', $dir, $this->errorMessage)
                );
            }
        }
        $this->dirCreated = true;
    }
}
