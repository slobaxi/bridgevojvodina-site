<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Player;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentShowTest extends TestCase
{
    use RefreshDatabase;

    private function createTournamentWithResults()
    {
        $user = User::factory()->create();
        $club = Club::create([
            'name' => 'NSBK', 'city' => 'NS', 'address' => 'A1', 'representative' => 'R1', 'email' => 'e@e.com', 'phone' => '1', 'status' => 'Active'
        ]);
        $player = Player::create(['first_name' => 'Slobodan', 'last_name' => 'Guzvica', 'club_id' => $club->id]);

        return Tournament::create([
            'title' => 'NS Team Cup',
            'description' => 'Desc',
            'details' => 'Details',
            'user_id' => $user->id,
            'team_results' => [
                'teams' => [
                    ['id' => 't1', 'name' => 'Team A', 'captain_id' => $player->id, 'player_ids' => [$player->id], 'total_vp' => 20.5],
                    ['id' => 't2', 'name' => 'Team B', 'captain_id' => $player->id, 'player_ids' => [$player->id], 'total_vp' => 15.0]
                ],
                'rounds' => [
                    [
                        'id' => 'r1', 'name' => 'Match 1',
                        'matches' => [
                            [
                                'id' => 'm1',
                                'home_team_id' => 't1', 'away_team_id' => 't2',
                                'home_imp' => 10, 'away_imp' => 5, 'home_vp' => 12.0, 'away_vp' => 8.0,
                                'home_lineup' => [['player_id' => $player->id, 'butler_score' => 1.5]],
                                'boards' => [
                                    [
                                        'board_number' => 1,
                                        'home_contract' => '4S', 'home_declarer' => 'S', 'home_lead' => 'HK', 'home_tricks' => 10, 'home_score' => 420,
                                        'away_contract' => '3NT', 'away_declarer' => 'N', 'away_lead' => 'D4', 'away_tricks' => 10, 'away_score' => 430,
                                        'home_imp' => 0, 'away_imp' => 1
                                    ]
                                ],
                                'open_ns_ids' => [$player->id, $player->id],
                                'open_ew_ids' => [$player->id, $player->id],
                                'closed_ns_ids' => [$player->id, $player->id],
                                'closed_ew_ids' => [$player->id, $player->id],
                            ]
                        ]
                    ]
                ]
            ]
        ]);
    }

    public function test_tournament_show_page_displays_standings_and_match_list()
    {
        $tournament = $this->createTournamentWithResults();

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertStatus(200);
        $response->assertSee('Team A');
        $response->assertSee('20.50');
        $response->assertSee('Match 1');
        $response->assertSee('Team B');
        $response->assertSee('10 : 5');
        $response->assertSee('View Details');
        
        // Detailed board info should NOT be on the show page
        $response->assertDontSee('3NT');
        $response->assertDontSee('Slobodan Guzvica');
    }

    public function test_tournament_show_page_displays_board_count_for_rounds()
    {
        $tournament = $this->createTournamentWithResults();
        
        $boardSet = \App\Models\BoardSet::create([
            'tournament_id' => $tournament->id,
            'name' => 'Round 1 Boards'
        ]);
        
        \App\Models\Board::create([
            'board_set_id' => $boardSet->id,
            'board_number' => 1,
            'vulnerability' => 'None',
            'cards_north' => ['S' => 'AKQJ', 'H' => '', 'D' => '', 'C' => ''],
            'cards_south' => ['S' => '32', 'H' => '', 'D' => '', 'C' => ''],
            'cards_east' => ['S' => '54', 'H' => '', 'D' => '', 'C' => ''],
            'cards_west' => ['S' => '76', 'H' => '', 'D' => '', 'C' => ''],
        ]);

        $results = $tournament->team_results;
        $results->rounds[0]->board_set_id = $boardSet->id;
        $tournament->team_results = $results;
        $tournament->save();

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertStatus(200);
        $response->assertSee('1 Boards');
    }

    public function test_tournament_match_page_displays_detailed_boards()
    {
        $tournament = $this->createTournamentWithResults();

        $response = $this->get("/tournaments/{$tournament->id}/round/r1/match/m1");

        $response->assertStatus(200);
        $response->assertSee('Team A');
        $response->assertSee('Team B');
        $response->assertSee('10 : 5');
        $response->assertSee('4');
        $response->assertSee('&spades;', false);
        $response->assertSee('3NT');
        $response->assertSee('420');
        $response->assertSee('430');
        $response->assertSee('Slobodan Guzvica');
    }

    public function test_tournament_board_page_displays_aggregated_results()
    {
        $tournament = $this->createTournamentWithResults();
        $boardSet = \App\Models\BoardSet::create([
            'tournament_id' => $tournament->id,
            'name' => 'Round 1 Boards',
        ]);

        \App\Models\Board::create([
            'board_set_id' => $boardSet->id,
            'board_number' => 1,
            'vulnerability' => 'None',
            'cards_north' => ['S' => 'AKQJ', 'H' => 'AKQ', 'D' => 'AKQ', 'C' => 'AKQ'],
            'cards_south' => ['S' => '6543', 'H' => '765', 'D' => '765', 'C' => '765'],
            'cards_east' => ['S' => 'T987', 'H' => 'T98', 'D' => 'T98', 'C' => 'T98'],
            'cards_west' => ['S' => '2', 'H' => 'J432', 'D' => 'J432', 'C' => 'J432'],
            'double_dummy_analysis' => [
                'engine' => 'pbn',
                'optimum_score' => 'NS 4S; 420',
                'table' => [
                    'N' => ['label' => 'North', 'strains' => ['NT' => 9, 'S' => 10, 'H' => 7, 'D' => 6, 'C' => 6]],
                    'S' => ['label' => 'South', 'strains' => ['NT' => 9, 'S' => 10, 'H' => 7, 'D' => 6, 'C' => 6]],
                    'E' => ['label' => 'East', 'strains' => ['NT' => 4, 'S' => 2, 'H' => 5, 'D' => 7, 'C' => 7]],
                    'W' => ['label' => 'West', 'strains' => ['NT' => 4, 'S' => 2, 'H' => 5, 'D' => 7, 'C' => 7]],
                ],
                'best_contract' => [
                    'contract' => '4S',
                    'strain' => 'S',
                    'declarer' => 'N',
                    'description' => '4 Spades by North. 420 points.',
                ],
            ],
        ]);

        $results = $tournament->team_results;
        $results->rounds[0]->board_set_id = $boardSet->id;
        $tournament->team_results = $results;
        $tournament->save();

        $response = $this->get("/tournaments/{$tournament->id}/round/r1/board/1");

        $response->assertStatus(200);
        $response->assertSee('Board 1');
        $response->assertSee('Dealer');
        $response->assertSee('N'); // Board 1 dealer
        $response->assertSee('Vuln');
        $response->assertSee('None'); // Board 1 vuln
        $response->assertSee('Team A');
        $response->assertSee('Team B');
        $response->assertSee('4');
        $response->assertSee('&spades;', false);
        $response->assertSee('3NT');
        $response->assertSee('Double Dummy Analysis');
        $response->assertSee('4 Spades by North. 420 points.');
        $response->assertSee('NS 4S; 420');
    }

    public function test_tournament_details_page_is_accessible()
    {
        $tournament = $this->createTournamentWithResults();

        $response = $this->get("/tournaments/{$tournament->id}/details");

        $response->assertStatus(200);
        $response->assertSee('Tournament Details');
        $response->assertSee('Details'); // from createTournamentWithResults
    }

    public function test_team_details_page_is_accessible_and_shows_players()
    {
        $tournament = $this->createTournamentWithResults();

        $response = $this->get("/tournaments/{$tournament->id}/teams/t1");

        $response->assertStatus(200);
        $response->assertSee('Team: Team A');
        $response->assertSee('Slobodan');
        $response->assertSee('Guzvica');
        $response->assertSee('Captain');
    }

    public function test_tournament_show_page_links_to_details_and_teams()
    {
        $tournament = $this->createTournamentWithResults();

        $response = $this->get("/tournaments/{$tournament->id}");

        $response->assertStatus(200);
        $response->assertSee('Tournament details');
        $response->assertSee("/tournaments/{$tournament->id}/details");
        $response->assertSee("/tournaments/{$tournament->id}/teams/t1");
        $response->assertSee("/tournaments/{$tournament->id}/teams/t2");
    }

    public function test_director_can_see_butler_button_on_draft()
    {
        $user = User::factory()->create(['role' => 'Director']);
        $club = Club::create([
            'name' => 'NSBK', 'city' => 'NS', 'address' => 'A1', 'representative' => 'R1', 'email' => 'e@e.com', 'phone' => '1', 'status' => 'Active'
        ]);
        $player = Player::create(['first_name' => 'Slobodan', 'last_name' => 'Guzvica', 'club_id' => $club->id]);

        $draft = \App\Models\TournamentConfiguration::create([
            'title' => 'Director Draft',
            'user_id' => User::factory()->create()->id, // Owned by someone else
            'team_results' => [
                'teams' => [['id' => 't1', 'name' => 'Team A', 'captain_id' => $player->id, 'player_ids' => [$player->id]]],
                'rounds' => [[
                    'id' => 'r1', 'name' => 'R1',
                    'matches' => [[
                        'id' => 'm1', 'home_team_id' => 't1', 'away_team_id' => 't1',
                        'open_ns_ids' => [$player->id], 'open_ew_ids' => [$player->id],
                        'closed_ns_ids' => [$player->id], 'closed_ew_ids' => [$player->id],
                        'boards' => [['board_number' => 1, 'home_score' => 420, 'away_score' => 430]]
                    ]]
                ]]
            ]
        ]);

        $response = $this->actingAs($user)->get("/tournaments/{$draft->id}");
        $response->assertStatus(200);
        $response->assertSee('Butler');
    }

    public function test_admin_can_see_butler_button_on_draft_even_if_not_published()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $club = Club::create([
            'name' => 'NSBK', 'city' => 'NS', 'address' => 'A1', 'representative' => 'R1', 'email' => 'e@e.com', 'phone' => '1', 'status' => 'Active'
        ]);
        $player = Player::create(['first_name' => 'Slobodan', 'last_name' => 'Guzvica', 'club_id' => $club->id]);

        $draft = \App\Models\TournamentConfiguration::create([
            'title' => 'Draft Tournament',
            'description' => 'Desc',
            'details' => 'Details',
            'user_id' => $user->id,
            'team_results' => [
                'teams' => [
                    ['id' => 't1', 'name' => 'Team A', 'captain_id' => $player->id, 'player_ids' => [$player->id], 'total_vp' => 0],
                    ['id' => 't2', 'name' => 'Team B', 'captain_id' => $player->id, 'player_ids' => [$player->id], 'total_vp' => 0]
                ],
                'rounds' => [
                    [
                        'id' => 'r1', 'name' => 'Match 1',
                        'matches' => [
                            [
                                'id' => 'm1',
                                'home_team_id' => 't1', 'away_team_id' => 't2',
                                'home_imp' => 10, 'away_imp' => 5, 'home_vp' => 12.0, 'away_vp' => 8.0,
                                'open_ns_ids' => [$player->id, $player->id],
                                'open_ew_ids' => [$player->id, $player->id],
                                'closed_ns_ids' => [$player->id, $player->id],
                                'closed_ew_ids' => [$player->id, $player->id],
                                'boards' => [
                                    ['board_number' => 1, 'home_score' => 420, 'away_score' => 430]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $response = $this->actingAs($user)->get("/tournaments/{$draft->id}");
        $response->assertStatus(200);
        $response->assertSee('Butler');
        
        $response = $this->actingAs($user)->get("/tournaments/{$draft->id}/details");
        $response->assertStatus(200);
        $response->assertSee('Butler');
    }
}
