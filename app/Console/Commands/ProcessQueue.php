<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:process 
                            {--tries=3 : Number of times to attempt a job}
                            {--timeout=300 : The number of seconds a child process can run}
                            {--max-jobs=50 : Number of jobs to process before stopping}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process queue jobs with stop-when-empty';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        // IMPORTANT:
        // Do not rely on the `jobs` table count here.
        // The default queue connection may be `redis`/`sqs`/etc, where there is no `jobs` table.
        // `queue:work --stop-when-empty` will exit automatically if there are no jobs.
        $connection = config('queue.default');
        $this->info("Processing queue connection [{$connection}]...");

        $this->call('queue:work', [
            'connection' => $connection,
            '--stop-when-empty' => true,
            '--tries' => $this->option('tries'),
            '--timeout' => $this->option('timeout'),
            '--max-jobs' => $this->option('max-jobs'),
        ]);

        return Command::SUCCESS;
    }
}

