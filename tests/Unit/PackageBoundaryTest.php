<?php

arch('platform package stays application independent')
    ->expect('OpenKOS')
    ->not->toUse(['App', 'Inertia', 'Spatie']);
