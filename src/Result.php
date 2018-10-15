<?php

namespace yandex\alisa;

class Result
{
    /**
     * @var string
     */
    protected $message;

    /**
     * @var string
     */
    protected $tts;

    /**
     * @var array
     */
    protected $button;

    /**
     * @var array
     */
    protected $context;

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param string $message
     */
    public function setMessage($message)
    {
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getTts()
    {
        return $this->tts;
    }

    /**
     * @param string $tts
     */
    public function setTts($tts)
    {
        $this->tts = $tts;
    }

    /**
     * @return array
     */
    public function getButton()
    {
        return $this->button;
    }

    /**
     * @param array $button
     */
    public function setButton($button)
    {
        $this->button = $button;
    }

    /**
     * @return array
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param array $context
     */
    public function setContext($context)
    {
        $this->context = $context;
    }
}