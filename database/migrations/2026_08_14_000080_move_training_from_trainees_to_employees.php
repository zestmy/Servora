<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The learner is an EMPLOYEE, not an LMS trainee.
 *
 * WHY THIS MOVED. Training shipped against `lms_users`, the training portal's
 * own login — which is invitation-only by design: somebody registers, a manager
 * approves them, and only then can they see anything. That is right for the SOP
 * library it was built for and wrong for this: a quiz nobody has been invited to
 * is a quiz nobody takes, and the whole point of the module is that every member
 * of staff is on it. 31 trainees had accounts against 57 active employees.
 *
 * The staff apps already solve this. An employee reaches them with the PIN they
 * already use to clock in and print labels — or, for the majority who have no
 * PIN, with a code emailed to the address on their record. That is the identity
 * training should hang off, so it does.
 *
 * THE LMS KEEPS ITS JOB. The SOP library, its logins and its approval flow are
 * untouched. Only the learning module moves.
 *
 * DATA. Every learner-owning table is empty except `training_attempts`, which
 * holds one real completed attempt. It is MAPPED rather than dropped: the
 * trainee's email is matched against the employee record with the same email in
 * the same company, and anything that cannot be matched is reported and left for
 * a human rather than silently deleted. Anyone who has genuinely taken a quiz
 * has earned the right not to lose it to a refactor.
 */
return new class extends Migration
{
    /** table => whether the learner may be absent (anonymous live player, outlet-wide assignment) */
    private const TABLES = [
        'training_attempts'        => true,
        'training_session_players' => true,
        'training_assignments'     => true,
        'training_certificates'    => false,
        'training_path_progress'   => false,
    ];

    /**
     * Does this index exist right now?
     *
     * Every structural step below is guarded by one of these, and that is not
     * belt-and-braces — MySQL DDL IS NOT TRANSACTIONAL. A migration that fails
     * halfway leaves the statements that already ran in place while the
     * migrations table records nothing, so the next run replays from the top
     * and dies on "duplicate column". This one did exactly that in production:
     * it added employee_id to training_attempts, then failed, and the retry
     * could not get past its own first step. Guarding each step lets a
     * half-applied run finish instead of needing the debris cleared by hand.
     */
    private function hasIndex(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }

    public function up(): void
    {
        /*
         * 1. Add the new column everywhere, nullable for now so the backfill
         *    has somewhere to land before anything is made compulsory.
         *
         * `after()` is conditional because only three of these five tables have
         * a company_id to sit after: training_session_players hangs off its
         * session and training_path_progress off its path, and neither carries
         * one. Naming a column that is not there is a hard error on MySQL and
         * SILENTLY IGNORED on SQLite, so the tests were green while production
         * could not get past the second table.
         */
        foreach (array_keys(self::TABLES) as $table) {
            if (Schema::hasColumn($table, 'employee_id')) {
                continue;
            }

            $anchor = Schema::hasColumn($table, 'company_id') ? 'company_id' : null;

            Schema::table($table, function (Blueprint $t) use ($anchor) {
                $column = $t->foreignId('employee_id')->nullable();

                if ($anchor) {
                    $column->after($anchor);
                }

                $column->constrained()->nullOnDelete();
            });
        }

        // 2. Map trainee -> employee by email within the same company, then by
        //    exact name. Email first because it is the identifier a person
        //    actually owns; names collide and are spelled differently on two
        //    systems more often than anybody expects.
        $map = [];

        foreach (DB::table('lms_users')->get(['id', 'company_id', 'email', 'name']) as $trainee) {
            $employeeId = DB::table('employees')
                ->where('company_id', $trainee->company_id)
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $trainee->email)])
                ->value('id');

            $employeeId ??= DB::table('employees')
                ->where('company_id', $trainee->company_id)
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $trainee->name)])
                ->value('id');

            if ($employeeId) {
                $map[$trainee->id] = $employeeId;
            }
        }

        foreach (array_keys(self::TABLES) as $table) {
            if (! Schema::hasColumn($table, 'lms_user_id')) {
                continue;
            }

            foreach ($map as $traineeId => $employeeId) {
                DB::table($table)->where('lms_user_id', $traineeId)->update(['employee_id' => $employeeId]);
            }

            $stranded = DB::table($table)->whereNotNull('lms_user_id')->whereNull('employee_id')->count();

            if ($stranded > 0) {
                logger()->warning("[training] {$table}: {$stranded} row(s) reference a trainee with no matching "
                    . 'employee. They keep their scores but are no longer attached to anybody; re-point them by '
                    . 'hand once the employee record exists.');
            }
        }

        /*
         * 3. Retire the old column.
         *
         * Every index that NAMES lms_user_id has to go before the column does,
         * and the list is exactly the four declared in the original migrations —
         * no more. `training_session_players` is the trap: its lms_user_id has a
         * foreign key but no index of its own, and MySQL silently creates one to
         * back the FK while SQLite does not. Dropping it by name therefore works
         * in production and fails every test with "no such index". Laravel's
         * dropConstrainedForeignId already removes whatever the engine created
         * for the FK, so there is nothing to do for that table here.
         */
        foreach ([
            ['training_attempts',      'training_attempt_user_idx',                    'index'],
            ['training_path_progress', 'training_path_progress_unique',                'unique'],
            ['training_certificates',  'training_certificates_company_id_lms_user_id_index', 'index'],
            ['training_assignments',   'training_assignments_lms_user_id_index',       'index'],
        ] as [$table, $name, $type]) {
            if (! $this->hasIndex($table, $name)) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($name, $type) {
                $type === 'unique' ? $t->dropUnique($name) : $t->dropIndex($name);
            });
        }

        foreach (array_keys(self::TABLES) as $table) {
            if (! Schema::hasColumn($table, 'lms_user_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('lms_user_id');
            });
        }

        // 4. Put the indexes back, on the column that now carries the learner.
        if (! $this->hasIndex('training_attempts', 'training_attempt_staff_idx')) {
            Schema::table('training_attempts', function (Blueprint $t) {
                $t->index(['company_id', 'employee_id', 'completed_at'], 'training_attempt_staff_idx');
            });
        }

        if (! $this->hasIndex('training_path_progress', 'training_path_progress_unique')) {
            Schema::table('training_path_progress', function (Blueprint $t) {
                $t->unique(['training_path_id', 'employee_id'], 'training_path_progress_unique');
            });
        }

        if (! $this->hasIndex('training_certificates', 'training_certificates_company_id_employee_id_index')) {
            Schema::table('training_certificates', function (Blueprint $t) {
                $t->index(['company_id', 'employee_id']);
            });
        }

        if (! $this->hasIndex('training_assignments', 'training_assignments_employee_id_index')) {
            Schema::table('training_assignments', function (Blueprint $t) {
                $t->index('employee_id');
            });
        }

        // 5. Where a learner is compulsory, say so in the schema rather than
        //    trusting every future caller to remember.
        foreach (self::TABLES as $table => $nullable) {
            if ($nullable) {
                continue;
            }

            DB::table($table)->whereNull('employee_id')->delete();

            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('employee_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->foreignId('lms_user_id')->nullable()->after('company_id')
                    ->constrained()->nullOnDelete();
            });
        }

        Schema::table('training_attempts', fn (Blueprint $t) => $t->dropIndex('training_attempt_staff_idx'));
        Schema::table('training_path_progress', fn (Blueprint $t) => $t->dropUnique('training_path_progress_unique'));
        Schema::table('training_certificates', fn (Blueprint $t) => $t->dropIndex(['company_id', 'employee_id']));
        Schema::table('training_assignments', fn (Blueprint $t) => $t->dropIndex(['employee_id']));

        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('employee_id'));
        }

        Schema::table('training_attempts', function (Blueprint $t) {
            $t->index(['company_id', 'lms_user_id', 'completed_at'], 'training_attempt_user_idx');
        });
        Schema::table('training_path_progress', function (Blueprint $t) {
            $t->unique(['training_path_id', 'lms_user_id'], 'training_path_progress_unique');
        });
        Schema::table('training_certificates', function (Blueprint $t) {
            $t->index(['company_id', 'lms_user_id']);
        });
        Schema::table('training_assignments', function (Blueprint $t) {
            $t->index('lms_user_id');
        });
    }
};
