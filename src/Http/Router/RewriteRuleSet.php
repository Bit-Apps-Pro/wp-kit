<?php

namespace BitApps\WPKit\Http\Router;

/**
 * Builds WordPress rewrite rules and query vars for a static page's route paths.
 */
final class RewriteRuleSet
{
    private string $_pageName;

    private array $_rules = [];

    private array $_queryVars = [];

    public function __construct(string $pageName)
    {
        $this->_pageName = trim($pageName, '/');
    }

    public function addPath(string $path): void
    {
        if (empty($this->_rules)) {
            $this->_rules["^{$this->_pageName}/?$"] = "index.php?pagename={$this->_pageName}";
        }

        preg_match_all(RoutePattern::PLACEHOLDER, $path, $regexMatched);
        $path         = $this->_pageName . '/' . $path . '/';
        $matchCount   = 1;
        $previousPath = "^{$this->_pageName}/?$";

        foreach ($regexMatched[0] as $param) {
            $param                 = trim($param, '{}?');
            $pathChunk             = substr($path, 0, strpos($path, "{{$param}}"));
            $pathChunkWithoutParam = '^' . $pathChunk . '?$';
            $pathChunkWithParam    = '^' . $pathChunk . '([^/]+)/?$';

            $path = str_replace("{{$param}}", '([^/]+)', $path);
            if (!isset($this->_rules[$pathChunkWithoutParam]) && strpos($pathChunkWithoutParam, '([^/]+)')) {
                $previousPath = trim(substr($pathChunkWithoutParam, 0, strpos($pathChunkWithoutParam, '([^/]+)') + \strlen('([^/]+)') + 1), '/') . '/?$';
            }
            $this->_rules[$pathChunkWithoutParam] = $this->_rules[$previousPath];
            $this->_rules[$pathChunkWithParam]    = $this->_rules[$pathChunkWithoutParam] . "&{$param}=\$matches[{$matchCount}]";
            ++$matchCount;
            $this->_queryVars[] = $param;
        }
    }

    public function rules(): array
    {
        return $this->_rules;
    }

    public function queryVars(): array
    {
        return array_values(array_unique($this->_queryVars));
    }
}
