<?php

return [

    /*
     * Guards checked, in order, to find "who is acting" when no actor is passed
     * explicitly. Empty = the default guard only. Apps with separate staff and
     * customer guards list both, e.g. ['admin', 'web'].
     */
    'actor_guards' => [],

];
