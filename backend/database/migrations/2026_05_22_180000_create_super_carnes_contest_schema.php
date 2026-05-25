<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->upgradeUsersTable();
        $this->createBranchesTable();
        $this->createCampaignsTable();
        $this->createWalletTables();
        $this->createPrizeTables();
        $this->createInvoiceTables();
        $this->createTournamentTables();
        $this->createWinnerTables();
        $this->createLiveScoreTables();
        $this->createAuditTables();
        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('live_score_commentary_events');
        Schema::dropIfExists('live_score_sync_runs');
        Schema::dropIfExists('live_score_settings');
        Schema::dropIfExists('promo_winner_contacts');
        Schema::dropIfExists('promo_winners');
        Schema::dropIfExists('phase_prizes');
        Schema::dropIfExists('match_predictions');
        Schema::dropIfExists('tournament_matches');
        Schema::dropIfExists('tournament_phases');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('daily_invoice_goals');
        Schema::dropIfExists('invoice_goal_settings');
        Schema::dropIfExists('registered_invoices');
        Schema::dropIfExists('prize_inventory_movements');
        Schema::dropIfExists('game_plays');
        Schema::dropIfExists('instant_win_windows');
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('prizes');
        Schema::dropIfExists('wallet_movements');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('audit_logs');
    }

    private function upgradeUsersTable(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'branch_id')) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->default('client')->after('branch_id');
            }
            if (! Schema::hasColumn('users', 'full_name')) {
                $table->string('full_name', 150)->nullable()->after('role');
            }
            if (! Schema::hasColumn('users', 'cedula')) {
                $table->string('cedula', 40)->nullable()->after('full_name');
            }
            if (! Schema::hasColumn('users', 'document_type')) {
                $table->string('document_type', 20)->default('cedula')->after('cedula');
            }
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path', 255)->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'password_hash')) {
                $table->string('password_hash')->nullable()->after('password');
            }
            if (! Schema::hasColumn('users', 'google_id')) {
                $table->string('google_id', 191)->nullable()->after('password_hash');
            }
            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('google_id');
            }
            if (! Schema::hasColumn('users', 'birthdate')) {
                $table->date('birthdate')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('users', 'resides_in_panama')) {
                $table->boolean('resides_in_panama')->default(false)->after('birthdate');
            }
            if (! Schema::hasColumn('users', 'is_employee')) {
                $table->boolean('is_employee')->default(false)->after('resides_in_panama');
            }
            if (! Schema::hasColumn('users', 'accepted_terms_at')) {
                $table->timestamp('accepted_terms_at')->nullable()->after('is_employee');
            }
            if (! Schema::hasColumn('users', 'registration_completed_at')) {
                $table->timestamp('registration_completed_at')->nullable()->after('accepted_terms_at');
            }
            if (! Schema::hasColumn('users', 'predictions_completed_at')) {
                $table->timestamp('predictions_completed_at')->nullable()->after('registration_completed_at');
            }
            if (! Schema::hasColumn('users', 'group_stage_goal_prediction')) {
                $table->unsignedSmallInteger('group_stage_goal_prediction')->nullable()->after('predictions_completed_at');
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('group_stage_goal_prediction');
            }
            if (! Schema::hasColumn('users', 'disqualified_at')) {
                $table->timestamp('disqualified_at')->nullable()->after('last_login_at');
            }
            if (! Schema::hasColumn('users', 'disqualification_reason')) {
                $table->text('disqualification_reason')->nullable()->after('disqualified_at');
            }
        });

        if (Schema::hasColumn('users', 'name')) {
            DB::statement("UPDATE users SET full_name = COALESCE(full_name, name)");
        }

        if (Schema::hasColumn('users', 'password')) {
            DB::statement("UPDATE users SET password_hash = COALESCE(password_hash, password)");
        }

        try {
            DB::statement('CREATE UNIQUE INDEX users_cedula_unique ON users (cedula)');
        } catch (Throwable) {
            // Index already exists.
        }

        try {
            DB::statement('CREATE UNIQUE INDEX users_google_id_unique ON users (google_id)');
        } catch (Throwable) {
            // Index already exists.
        }
    }

    private function createBranchesTable(): void
    {
        if (Schema::hasTable('branches')) {
            return;
        }

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('code', 40)->unique();
            $table->string('address', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    private function createCampaignsTable(): void
    {
        if (Schema::hasTable('campaigns')) {
            return;
        }

        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->decimal('invoice_min_amount_for_shot', 10, 2)->default(25);
            $table->decimal('amount_per_point', 10, 2)->default(25);
            $table->unsignedInteger('points_per_block')->default(1);
            $table->unsignedInteger('daily_max_points')->default(1000);
            $table->unsignedInteger('daily_max_invoices')->default(100);
            $table->unsignedInteger('coupon_ttl_hours')->default(72);
            $table->boolean('games_enabled')->default(false);
            $table->boolean('major_prizes_enabled')->default(false);
            $table->boolean('invoice_scan_enabled')->default(true);
            $table->boolean('redemption_enabled')->default(false);
            $table->timestamps();
        });
    }

    private function createWalletTables(): void
    {
        if (! Schema::hasTable('wallets')) {
            Schema::create('wallets', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->unsignedInteger('goals_balance')->default(0);
                $table->unsignedInteger('shots_balance')->default(0);
                $table->unsignedInteger('lifetime_goals_earned')->default(0);
                $table->unsignedInteger('lifetime_shots_earned')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('wallet_movements')) {
            Schema::create('wallet_movements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('wallet_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('campaign_id')->nullable();
                $table->string('type', 50);
                $table->string('resource_type', 50)->nullable();
                $table->unsignedBigInteger('resource_id')->nullable();
                $table->integer('goals_delta')->default(0);
                $table->integer('shots_delta')->default(0);
                $table->text('notes')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    private function createPrizeTables(): void
    {
        if (! Schema::hasTable('prizes')) {
            Schema::create('prizes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('campaign_id');
                $table->string('name', 150);
                $table->string('slug', 150);
                $table->text('description')->nullable();
                $table->string('category', 30);
                $table->string('redemption_type', 30);
                $table->unsignedInteger('points_cost')->nullable();
                $table->unsignedInteger('shots_cost')->nullable();
                $table->unsignedInteger('total_stock')->default(0);
                $table->unsignedInteger('reserved_stock')->default(0);
                $table->unsignedInteger('delivered_stock')->default(0);
                $table->string('image_url', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('instant_win_windows')) {
            Schema::create('instant_win_windows', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('prize_id');
                $table->dateTime('opens_at');
                $table->dateTime('closes_at');
                $table->boolean('is_consumed')->default(false);
                $table->unsignedBigInteger('consumed_by_user_id')->nullable();
                $table->dateTime('consumed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('game_plays')) {
            Schema::create('game_plays', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('campaign_id');
                $table->string('game_type', 50);
                $table->string('client_choice', 120)->nullable();
                $table->string('result_type', 50);
                $table->unsignedBigInteger('prize_id')->nullable();
                $table->unsignedBigInteger('window_id')->nullable();
                $table->unsignedInteger('shots_spent')->default(1);
                $table->dateTime('played_at');
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('coupons')) {
            Schema::create('coupons', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('campaign_id');
                $table->unsignedBigInteger('prize_id');
                $table->string('source_type', 40);
                $table->uuid('code')->unique();
                $table->text('qr_payload');
                $table->string('status', 30)->default('generated');
                $table->dateTime('expires_at');
                $table->dateTime('delivered_at')->nullable();
                $table->unsignedBigInteger('delivered_by_user_id')->nullable();
                $table->unsignedBigInteger('delivered_branch_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('prize_inventory_movements')) {
            Schema::create('prize_inventory_movements', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('prize_id');
                $table->string('movement_type', 50);
                $table->integer('quantity');
                $table->unsignedBigInteger('related_coupon_id')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by_user_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    private function createInvoiceTables(): void
    {
        if (! Schema::hasTable('registered_invoices')) {
            Schema::create('registered_invoices', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('campaign_id')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->string('cufe', 191)->unique();
                $table->longText('qr_raw_text');
                $table->string('invoice_number', 80)->nullable();
                $table->string('fiscal_document_type', 80)->nullable();
                $table->dateTime('issued_at')->nullable();
                $table->decimal('purchase_amount', 10, 2);
                $table->unsignedInteger('points_awarded')->default(0);
                $table->unsignedInteger('shots_awarded')->default(0);
                $table->boolean('daily_points_capped')->default(false);
                $table->boolean('daily_invoice_limit_hit')->default(false);
                $table->string('status', 40)->default('pending_validation');
                $table->string('validation_status', 30)->default('pending');
                $table->text('validation_notes')->nullable();
                $table->timestamp('dgi_checked_at')->nullable();
                $table->json('dgi_response_payload')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('registered_invoices', function (Blueprint $table): void {
                if (! Schema::hasColumn('registered_invoices', 'validation_status')) {
                    $table->string('validation_status', 30)->default('pending')->after('status');
                }
                if (! Schema::hasColumn('registered_invoices', 'validation_notes')) {
                    $table->text('validation_notes')->nullable()->after('validation_status');
                }
                if (! Schema::hasColumn('registered_invoices', 'dgi_checked_at')) {
                    $table->timestamp('dgi_checked_at')->nullable()->after('validation_notes');
                }
                if (! Schema::hasColumn('registered_invoices', 'dgi_response_payload')) {
                    $table->json('dgi_response_payload')->nullable()->after('dgi_checked_at');
                }
            });
        }

        if (! Schema::hasTable('invoice_goal_settings')) {
            Schema::create('invoice_goal_settings', function (Blueprint $table): void {
                $table->id();
                $table->boolean('is_enabled')->default(true);
                $table->decimal('goal_value', 10, 2)->default(1);
                $table->decimal('min_purchase_amount', 10, 2)->default(25);
                $table->unsignedTinyInteger('max_invoice_age_days')->default(1);
                $table->boolean('one_invoice_per_day')->default(false);
                $table->string('validation_mode', 20)->default('api');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('daily_invoice_goals')) {
            Schema::create('daily_invoice_goals', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('phase_id')->nullable();
                $table->string('invoice_number', 100);
                $table->decimal('purchase_amount', 10, 2)->nullable();
                $table->date('invoice_date');
                $table->decimal('goal_points_awarded', 10, 2)->default(1);
                $table->string('validation_status', 30)->default('pending');
                $table->text('validation_notes')->nullable();
                $table->timestamps();
            });
        }
    }

    private function createTournamentTables(): void
    {
        if (! Schema::hasTable('tournament_phases')) {
            Schema::create('tournament_phases', function (Blueprint $table): void {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 80)->unique();
                $table->unsignedInteger('stage_order')->default(1);
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->unsignedInteger('exact_score_points')->default(3);
                $table->unsignedInteger('outcome_points')->default(1);
                $table->boolean('reset_phase_table')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('external_team_id')->nullable();
                $table->unsignedBigInteger('external_country_id')->nullable();
                $table->string('name', 120);
                $table->string('code', 20)->nullable();
                $table->unsignedInteger('ranking_fifa')->nullable();
                $table->string('group_label', 20)->nullable();
                $table->string('flag_emoji', 10)->nullable();
                $table->string('provider_logo_url', 255)->nullable();
                $table->string('provider_flag_path', 120)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('tournament_matches')) {
            Schema::create('tournament_matches', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('external_fixture_id')->nullable();
                $table->unsignedBigInteger('external_match_id')->nullable();
                $table->unsignedBigInteger('phase_id');
                $table->unsignedInteger('match_number')->nullable();
                $table->unsignedBigInteger('external_group_id')->nullable();
                $table->string('group_label', 20)->nullable();
                $table->string('round_label', 80)->nullable();
                $table->string('stage_label', 120)->nullable();
                $table->string('venue_name', 180)->nullable();
                $table->unsignedBigInteger('home_team_id');
                $table->unsignedBigInteger('away_team_id');
                $table->string('favorite_side', 10)->default('none');
                $table->dateTime('kickoff_at');
                $table->unsignedTinyInteger('home_score')->nullable();
                $table->unsignedTinyInteger('away_score')->nullable();
                $table->string('status', 20)->default('scheduled');
                $table->string('provider', 40)->nullable();
                $table->string('provider_status', 60)->nullable();
                $table->string('provider_competition_name', 180)->nullable();
                $table->string('kickoff_timezone', 40)->nullable();
                $table->dateTime('live_score_last_synced_at')->nullable();
                $table->dateTime('commentary_last_synced_at')->nullable();
                $table->json('raw_provider_payload')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('match_predictions')) {
            Schema::create('match_predictions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('match_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('phase_id');
                $table->unsignedTinyInteger('predicted_home_score');
                $table->unsignedTinyInteger('predicted_away_score');
                $table->unsignedInteger('points_awarded')->default(0);
                $table->string('result_type', 20)->default('pending');
                $table->timestamps();
                $table->unique(['match_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('phase_prizes')) {
            Schema::create('phase_prizes', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('phase_id');
                $table->unsignedInteger('ranking_from');
                $table->unsignedInteger('ranking_to');
                $table->string('football_role', 80);
                $table->string('prize_title', 150);
                $table->string('prize_type', 120);
                $table->unsignedInteger('stock')->default(0);
                $table->timestamps();
            });
        }
    }

    private function createWinnerTables(): void
    {
        if (! Schema::hasTable('promo_winners')) {
            Schema::create('promo_winners', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('phase_id');
                $table->unsignedBigInteger('user_id');
                $table->unsignedInteger('leaderboard_position');
                $table->decimal('total_points', 10, 2)->default(0);
                $table->unsignedInteger('exact_hits')->default(0);
                $table->unsignedInteger('invoice_count')->default(0);
                $table->decimal('invoice_total_amount', 10, 2)->default(0);
                $table->unsignedInteger('goal_prediction_delta')->nullable();
                $table->timestamp('ranking_timestamp')->nullable();
                $table->string('selection_reason', 30);
                $table->string('status', 30)->default('selected');
                $table->unsignedBigInteger('replacement_for_winner_id')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('selected_at')->nullable();
                $table->timestamp('last_contact_at')->nullable();
                $table->timestamp('responded_at')->nullable();
                $table->timestamp('disqualified_at')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->unique(['phase_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('promo_winner_contacts')) {
            Schema::create('promo_winner_contacts', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('promo_winner_id');
                $table->string('contact_type', 30);
                $table->string('contact_status', 30);
                $table->timestamp('contacted_at');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    private function createLiveScoreTables(): void
    {
        if (! Schema::hasTable('live_score_settings')) {
            Schema::create('live_score_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('provider_name', 50)->default('live_score_api');
                $table->boolean('is_enabled')->default(false);
                $table->string('competition_id', 120)->nullable();
                $table->string('competition_ids', 255)->nullable();
                $table->string('season', 20)->nullable();
                $table->string('lang', 10)->default('es');
                $table->date('sync_from_date')->nullable();
                $table->date('sync_to_date')->nullable();
                $table->boolean('auto_sync_commentary')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('live_score_sync_runs')) {
            Schema::create('live_score_sync_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('sync_type', 30);
                $table->string('status', 30);
                $table->unsignedBigInteger('requested_by_user_id')->nullable();
                $table->unsignedInteger('records_created')->default(0);
                $table->unsignedInteger('records_updated')->default(0);
                $table->unsignedInteger('records_skipped')->default(0);
                $table->json('context')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('live_score_commentary_events')) {
            Schema::create('live_score_commentary_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tournament_match_id');
                $table->unsignedBigInteger('external_match_id')->nullable();
                $table->unsignedBigInteger('external_event_id')->unique();
                $table->string('event_type', 50);
                $table->unsignedInteger('minute')->nullable();
                $table->string('second_label', 20)->nullable();
                $table->unsignedInteger('match_second')->nullable();
                $table->text('comment_text')->nullable();
                $table->string('text_label', 120)->nullable();
                $table->decimal('pos_x', 6, 2)->nullable();
                $table->decimal('pos_y', 6, 2)->nullable();
                $table->string('side', 20)->nullable();
                $table->unsignedBigInteger('external_team_id')->nullable();
                $table->string('team_name', 120)->nullable();
                $table->unsignedBigInteger('external_player_id')->nullable();
                $table->string('player_name', 120)->nullable();
                $table->unsignedBigInteger('external_player_2_id')->nullable();
                $table->string('player_2_name', 120)->nullable();
                $table->timestamp('provider_created_at')->nullable();
                $table->timestamp('provider_updated_at')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();
            });
        }
    }

    private function createAuditTables(): void
    {
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('actor_role', 20)->nullable();
            $table->string('event_type', 100);
            $table->string('entity_type', 60);
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    private function seedDefaults(): void
    {
        if (DB::table('invoice_goal_settings')->count() === 0) {
            DB::table('invoice_goal_settings')->insert([
                'is_enabled' => true,
                'goal_value' => 1,
                'min_purchase_amount' => 25,
                'max_invoice_age_days' => 1,
                'one_invoice_per_day' => false,
                'validation_mode' => 'api',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('live_score_settings')->count() === 0) {
            DB::table('live_score_settings')->insert([
                'provider_name' => 'live_score_api',
                'is_enabled' => false,
                'lang' => 'es',
                'auto_sync_commentary' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('campaigns')->count() === 0) {
            DB::table('campaigns')->insert([
                'name' => 'Polla Mundialista Super Carnes 2026',
                'description' => 'Campana oficial para la fase de grupos del Mundial 2026.',
                'status' => 'active',
                'starts_at' => '2026-05-22 00:00:00',
                'ends_at' => '2026-07-03 23:59:59',
                'invoice_min_amount_for_shot' => 25,
                'amount_per_point' => 25,
                'points_per_block' => 1,
                'daily_max_points' => 1000,
                'daily_max_invoices' => 100,
                'coupon_ttl_hours' => 72,
                'games_enabled' => false,
                'major_prizes_enabled' => false,
                'invoice_scan_enabled' => true,
                'redemption_enabled' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (DB::table('tournament_phases')->count() === 0) {
            $phases = [
                ['name' => 'Fase de Grupos', 'slug' => 'fase-grupos', 'stage_order' => 1, 'starts_at' => '2026-05-22 00:00:00', 'ends_at' => '2026-07-03 23:59:59', 'exact_score_points' => 3, 'outcome_points' => 1, 'reset_phase_table' => false, 'is_active' => true],
                ['name' => 'Dieciseisavos de Final', 'slug' => 'dieciseisavos', 'stage_order' => 2, 'starts_at' => '2026-07-04 00:00:00', 'ends_at' => '2026-07-08 23:59:59', 'exact_score_points' => 5, 'outcome_points' => 2, 'reset_phase_table' => false, 'is_active' => false],
                ['name' => 'Octavos de Final', 'slug' => 'octavos', 'stage_order' => 3, 'starts_at' => '2026-07-09 00:00:00', 'ends_at' => '2026-07-13 23:59:59', 'exact_score_points' => 5, 'outcome_points' => 2, 'reset_phase_table' => false, 'is_active' => false],
                ['name' => 'Cuartos de Final', 'slug' => 'cuartos', 'stage_order' => 4, 'starts_at' => '2026-07-14 00:00:00', 'ends_at' => '2026-07-17 23:59:59', 'exact_score_points' => 5, 'outcome_points' => 2, 'reset_phase_table' => false, 'is_active' => false],
                ['name' => 'Semifinal y Final', 'slug' => 'semifinal-final', 'stage_order' => 5, 'starts_at' => '2026-07-18 00:00:00', 'ends_at' => '2026-07-20 23:59:59', 'exact_score_points' => 7, 'outcome_points' => 3, 'reset_phase_table' => false, 'is_active' => false],
            ];

            foreach ($phases as $phase) {
                DB::table('tournament_phases')->insert(array_merge($phase, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }
};
