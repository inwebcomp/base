<?php

namespace InWeb\Base\Console;

use Illuminate\Filesystem\Filesystem;

trait RenamesStubs
{
    public array $stubsToRename = [];

    /**
     * Rename the stubs with PHP file extensions.
     *
     * @return void
     */
    protected function renameStubs()
    {
        foreach ($this->stubsToRename() as $stub) {
            (new Filesystem)->move($stub, str_replace('.stub', '.php', $stub));
        }
    }

    public function addStubToRename($path)
    {
        $this->stubsToRename[] = $path;
    }
}
