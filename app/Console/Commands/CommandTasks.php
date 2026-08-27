<?php

namespace App\Console\Commands;

use App\Http\Repositories\KualionDataRepository;
use Illuminate\Console\Command;

class CommandTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:command-tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            $kualionRepo = new KualionDataRepository();
            $kualionRepo->dispatchTables();
        } catch (\Throwable $th) {
            error_log(
                date("[Y-m-d H:i:s]") . $th . PHP_EOL,
                3,
                storage_path('logs/TaskErrors.log')
            );
        }
    }
}
