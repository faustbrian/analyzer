<?php

namespace Tests\Fixtures\Routes;

class SyntaxError
{
    public function brokenMethod()
    {
        return route('posts.index'  // Missing closing parenthesis
    }
}
