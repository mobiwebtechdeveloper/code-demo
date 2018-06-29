<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * This Class used as REST API for Live
 * @package   CodeIgniter
 * @category  Controller
 * @author    Fantasy Sports Team
 */
class Cricket extends CI_Controller {

    public $API_URL = "";
    private $PLAYER_TOTAL_POINT = 0;
    public $config = array();

    function __construct() {
        parent::__construct();

        $this->config->api_url = 'https://rest.cricketapi.com';
        $this->config->year = '2018';
        $this->config->access_key = '***';
        $this->config->secret_key = '***';
        $this->config->app_id = '***';
        $this->config->device_id = '***';
    }

    /**
     * Function Name: get_access_token
     * Description:   To create access token
     */
    public function get_access_token() {

        $param = array(
            'access_key' => $this->config->access_key,
            'secret_key' => $this->config->secret_key,
            'app_id' => $this->config->app_id,
            'device_id' => $this->config->device_id
        );

        $ch = curl_init($this->config->api_url . '/rest/v2/auth/');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);
        $result = json_decode($response, true);
        return $result['auth']['access_token'];
    }

    /**
     * Function Name: getMatchesSeason
     * Description:   To get match season data
     */
    function getMatchesSeason() {
        $return['code'] = 200;
        $return['response'] = new stdClass();
        $access_token = $this->get_access_token();
        $url = $this->config->api_url . '/rest/v2/schedule/?access_token=' . $access_token;
        $series_data = @file_get_contents($url);
        if (!$series_data) {
            echo "Records not found";
            exit;
        }
        $series_array = @json_decode(gzdecode($series_data), TRUE);
        if ($series_array['status_code'] == 200 && !empty($series_array['data'])) {
            $months = $series_array['data']['months'][0]['days'];
            foreach ($months as $key => $season) {
                if (!empty($season['matches'])) {
                    $status = $season['matches'][0]['status'];
                    $related_name = $season['matches'][0]['related_name'];
                    $name = $season['matches'][0]['name'];
                    $short_name = $season['matches'][0]['short_name'];
                    $format = $season['matches'][0]['format'];
                    $match_id = $season['matches'][0]['key'];
                    $start_date_time = date('Y-m-d H:i', strtotime($season['matches'][0]['start_date']['iso']));
                    $start_date = date('Y-m-d', strtotime($season['matches'][0]['start_date']['iso']));
                    $start_time = date('H:i', strtotime($season['matches'][0]['start_date']['iso']));

                    /** series data * */
                    $series = $season['matches'][0]['season'];
                    $series_id = $series['key'];
                    $series_name = $series['name'];
                    $this->setSeries($series_id, $series_name);

                    /** local team data * */
                    $teams = $season['matches'][0]['teams'];
                    $localteam = $teams['a'];
                    $localteam_id = $localteam['match']['season_team_key'];
                    $localteam_name = $localteam['name'];
                    $localteam_key = $localteam['key'];
                    $this->setTeam($localteam_key, $localteam_name, $localteam_id);

                    /** visitor team data * */
                    $visitorteam = $teams['b'];
                    $visitorteam_id = $visitorteam['match']['season_team_key'];
                    $visitorteam_name = $visitorteam['name'];
                    $visitorteam_key = $visitorteam['key'];
                    $this->setTeam($visitorteam_key, $visitorteam_name, $visitorteam_id);
                    $format_txt = "";
                    if (strtolower($format) == "test") {
                        $format_txt = "Test";
                    } else if (strtolower($format) == "t20") {
                        $format_txt = "Twenty20";
                    } else if (strtolower($format) == "one-day") {
                        $format_txt = "ODI";
                    }
                    $matchArray = array(
                        'match_id' => $match_id,
                        'series_id' => $series_id,
                        'match_date' => $start_date,
                        'match_time' => $start_time,
                        'match_type' => $format_txt,
                        'match_num' => $related_name,
                        'localteam_id' => $localteam_id,
                        'localteam' => $localteam_name,
                        'localteam_key' => $localteam_key,
                        'visitorteam_id' => $visitorteam_id,
                        'visitorteam' => $visitorteam_name,
                        'visitorteam_key' => $visitorteam_key,
                        'squads_file' => "",
                        'match_date_time' => $start_date_time,
                        'prediction_code' => commonUniqueCode()
                    );
                    $this->setMatch($match_id, $matchArray);
                }
            }
        }
        return true;
    }

    /**
     * Function Name: setTeam
     * Description:   Set team common function
     */
    function setTeam($team_id, $team_name, $matchTeamId) {
        $option = array(
            'table' => 'team',
            'where' => array(
                'team_key' => $team_id,
            )
        );
        $isTeam = $this->common_model->customGet($option);
        if (empty($isTeam)) {
            $option = array(
                'table' => 'team',
                'data' => array(
                    'team_key' => $team_id,
                    'team_name' => $team_name,
                    'season_team_key' => $matchTeamId
                )
            );
            $this->common_model->customInsert($option);
        }
    }

    /**
     * Function Name: setMatch
     * Description:   Set match common function
     */
    function setMatch($match_id, $matchArray) {
        $options = array(
            'table' => 'matches',
            'where' => array(
                'match_id' => $match_id,
            )
        );
        $isMatch = $this->common_model->customGet($options);
        if (empty($isMatch)) {
            $options = array(
                'table' => 'matches',
                'data' => $matchArray
            );
            $this->common_model->customInsert($options);
        }
    }

    /**
     * Function Name: setSeries
     * Description:   Set series common function
     */
    function setSeries($series_id, $series_name) {
        $options = array(
            'table' => 'series',
            'where' => array(
                'sid' => $series_id
            )
        );
        $isSeries = $this->common_model->customGet($options);
        if (empty($isSeries)) {
            $options = array(
                'table' => 'series',
                'data' => array(
                    'sid' => $series_id,
                    'name' => $series_name,
                    'file_path' => "",
                    'squads_file' => ""
                )
            );
            $this->common_model->customInsert($options);
        }
        return true;
    }

    /**
     * Function Name: getMatchPlayers
     * Description:   get match player details
     */
    function getMatchPlayers() {
        $return['code'] = 200;
        $return['response'] = new stdClass();
        $access_token = $this->get_access_token();
        $options = array(
            'table' => 'series',
            'where' => array('series.squads_file' => 0)
        );
        $series = $this->common_model->customGet($options);
        $teams = array();
        if (!empty($series)) {
            foreach ($series as $rows) {
                $SEASON_KEY = $rows->sid;
                $options = array(
                    'table' => 'matches',
                    'select' => 'matches.localteam_id,matches.visitorteam_id',
                    'where' => array('matches.series_id' => $SEASON_KEY)
                );
                $seriesMatches = $this->common_model->customGet($options);
                foreach ($seriesMatches as $matches) {
                    $teams[] = $matches->localteam_id;
                    $teams[] = $matches->visitorteam_id;
                }
                $teams = array_unique($teams);
                foreach ($teams as $matchKey) {
                    $SEASON_TEAM_KEY = $matchKey;
                    $url = $this->config->api_url . '/rest/v2/season/' . $SEASON_KEY . '/team/' . $SEASON_TEAM_KEY . '/?access_token=' . $access_token;
                    $series_data = @file_get_contents($url);
                    $series_array = @json_decode(gzdecode($series_data), TRUE);
                    if ($series_array['status'] == 1 && !empty($series_array['data'])) {
                        $card_name = $series_array['data']['card_name'];
                        $team_name = $series_array['data']['name'];
                        $players = $series_array['data']['players'];
                        foreach ($players as $player) {
                            $player_key = $player['key'];
                            $player_name = $player['card_name'];
                            $playing_role = strtoupper($player['playing_role']);
                            $role = "BATSMAN";
                            if ($playing_role == "BOWLER") {
                                $role = "BOWLER";
                            } else if ($playing_role == "BATSMAN") {
                                $role = "BATSMAN";
                            } else if ($playing_role == "WICKET-KEEPER") {
                                $role = "WICKETKEEPER";
                            } else if ($playing_role == "ALLROUNDER") {
                                $role = "ALLROUNDER";
                            }
                            $options = array(
                                'table' => 'match_player',
                                'where' => array(
                                    'player_id' => $player_key,
                                    'series_id' => $SEASON_KEY,
                                    'team_id' => $SEASON_TEAM_KEY
                                )
                            );
                            $isPlayer = $this->common_model->customGet($options);
                            if (empty($isPlayer)) {
                                $options = array(
                                    'table' => 'match_player',
                                    'data' => array(
                                        'series_id' => $SEASON_KEY,
                                        'team_id' => $SEASON_TEAM_KEY,
                                        'team' => $team_name,
                                        'player_id' => $player_key,
                                        'player_name' => $player_name,
                                        'play_role' => $role,
                                        'test' => 1,
                                        'odi' => 1,
                                        't20' => 1,
                                    )
                                );
                                $this->common_model->customInsert($options);
                            }
                        }
                    }
                }
                $options = array(
                    'table' => 'series',
                    'data' => array('series.squads_file' => 1),
                    'where' => array('sid' => $SEASON_KEY)
                );
                $this->common_model->customUpdate($options);
            }
        }
        return true;
    }

    /**
     * Function Name: matchPlayerPoints
     * Description:   get match player points
     */
    function matchPlayerPoints() {
        $access_token = $this->get_access_token();
        $options = array(
            'table' => 'matches',
            'select' => 'match_id,series_id',
            'where' => array('update_credit_squad' => 0)
        );
        $seriesMatches = $this->common_model->customGet($options);
        foreach ($seriesMatches as $match) {
            $SEASON_TEAM_KEY = $match->match_id;
            $SEASON_KEY = $match->series_id;
            $url = $this->config->api_url . '/rest/v3/fantasy-match-credits/' . $SEASON_TEAM_KEY . '/?access_token=' . $access_token;
            $series_data = @file_get_contents($url);
            $series_array = @json_decode($series_data, TRUE);
            if ($series_array['status'] == 1 && !empty($series_array['data'])) {
                $matchStatus = $series_array['data']['match']['status'];
                $matchFantasyPoints = $series_array['data']['fantasy_points'];
                if ($matchStatus == "notstarted") {
                    foreach ($matchFantasyPoints as $player) {
                        $credit_value = $player['credit_value'];
                        $player_id = $player['player'];
                        $options = array(
                            'table' => 'match_player',
                            'where' => array(
                                'player_id' => $player_id,
                                'series_id' => $SEASON_KEY
                            )
                        );
                        $isPlayer = $this->common_model->customGet($options);
                        if (!empty($isPlayer)) {
                            $options = array(
                                'table' => 'match_player',
                                'data' => array(
                                    't20_credit_points' => $credit_value,
                                    'odi_credit_points' => $credit_value,
                                    'test_credit_points' => $credit_value,
                                ),
                                'where' => array(
                                    'player_id' => $player_id,
                                    'series_id' => $SEASON_KEY
                                )
                            );
                            $this->common_model->customUpdate($options);
                        }
                    }
                }
            }

            $options = array(
                'table' => 'matches',
                'data' => array('update_credit_squad' => 1),
                'where' => array('match_id' => $SEASON_TEAM_KEY)
            );
            $this->common_model->customUpdate($options);
        }
    }

}

