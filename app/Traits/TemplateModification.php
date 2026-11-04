<?php

namespace App\Traits;

trait TemplateModification
{
    public function getSubject($subject)
    {
        return $this->replaceTags($subject);
    }

    public function getBody($body)
    {
        return $this->replaceTags($body);
    }

    public function replaceTags($content)
    {
        $pattern = $this->getTagsPattern();
        $replacement = $this->applyQuote($this->getTagsReplacement());

        return $this->revertQuote(preg_replace($pattern, $replacement, $content));
    }

    public function getTagsPattern()
    {
        $pattern = [];

        foreach ($this->getTags() as $tag) {
            $pattern[] = "/" . $tag . "/";
        }

        return $pattern;
    }

    public function getTags()
    {
        return [];
    }

    public function getTagsReplacement()
    {
        return [];
    }

    public function applyQuote($vars)
    {
        $new_vars = [];

        foreach ($vars as $var) {
            $new_vars[] = preg_quote($var);
        }

        return $new_vars;
    }

    public function revertQuote($content)
    {
        return str_replace('\\', '', $content);
    }
}
