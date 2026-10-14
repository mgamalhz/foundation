<?php

namespace Mgamalhz\Lifecycle\Commands;

use Illuminate\Console\Command;
use Mgamalhz\Lifecycle\Concerns\HasLifecycle;

/**
 * Prints a model's lifecycle so a developer — or a coding agent — can see the
 * real rules before changing them:
 *
 *     php artisan lifecycle:show Order
 *     php artisan lifecycle:show "App\Models\Order" --mermaid
 */
class ShowLifecycleCommand extends Command
{
    protected $signature = 'lifecycle:show {model : Model class, e.g. Order or App\\Models\\Order} {--mermaid : Print a Mermaid state diagram}';

    protected $description = 'Show the declared states and transitions of a lifecycle-managed model';

    public function handle(): int
    {
        $class = $this->resolveClass($this->argument('model'));

        if ($class === null) {
            $this->error("Model [{$this->argument('model')}] not found, or it does not use HasLifecycle.");

            return self::FAILURE;
        }

        $machine = $class::stateMachine();

        if ($this->option('mermaid')) {
            $this->line($machine->toMermaid());

            return self::SUCCESS;
        }

        $this->info("{$class} — attribute: {$machine->attribute()}");
        $this->line('Initial: '.(implode(', ', $machine->initialStates()) ?: '(any)'));

        $rows = collect($machine->states())->map(fn (string $state) => [
            $state,
            $machine->isTerminal($state) ? '— terminal —' : collect($machine->allowedFrom($state))
                ->map(fn (string $to) => $machine->guardsFor($to) === [] ? $to : "{$to} (guarded)")
                ->implode(', '),
        ]);

        $this->table(['From', 'Allowed to'], $rows->all());

        return self::SUCCESS;
    }

    /** @return class-string|null */
    private function resolveClass(string $name): ?string
    {
        foreach ([$name, 'App\\Models\\'.$name] as $candidate) {
            if (class_exists($candidate) && in_array(HasLifecycle::class, class_uses_recursive($candidate), true)) {
                return $candidate;
            }
        }

        return null;
    }
}
