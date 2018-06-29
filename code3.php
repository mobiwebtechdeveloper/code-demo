<?php

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * This Class used as REST API for Match
 * @package   CodeIgniter
 * @category  Controller
 * @author    MobiwebTech Team
 */
class Match extends Common_API_Controller {

    function __construct() {
        parent::__construct();
        $tables = $this->config->item('tables', 'ion_auth');
        $this->lang->load('en', 'english');
    }

    /**
     * Function Name: series
     * Description:   To recent series list
     */
    function series_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $options = array(
                'table' => 'series',
                'select' => 'series.sid as series_id,series.name',
                'join' => array('matches' => 'matches.series_id = series.sid',
                    'match_player' => 'match_player.series_id = series.sid'),
                'where' => array('series.play_status' => '1', 'matches.status !=' => "closed"),
                'group_by' => 'series.sid'
            );
            $series = $this->common_model->customGet($options);
            $allSeries = array();
            foreach ($series as $rows) {
                $temp['series_id'] = $rows->series_id;
                $temp['name'] = $rows->name;
                $sql = "SELECT matches.match_date
                            FROM matches
                            WHERE matches.series_id = '" . $rows->series_id . "' "
                        . "ORDER BY matches.match_date ASC LIMIT 1 OFFSET 0";
                $fromDate = $this->common_model->customQuery($sql, true);
                $sql = "SELECT matches.match_date
                            FROM matches
                            WHERE matches.series_id = '" . $rows->series_id . "' "
                        . "ORDER BY matches.match_date DESC LIMIT 1 OFFSET 0";
                $toDate = $this->common_model->customQuery($sql, true);
                $temp['from_date'] = (!empty($fromDate)) ? $fromDate->match_date : "";
                $temp['from_to'] = (!empty($toDate)) ? $toDate->match_date : "";
                $temp['image'] = base_url() . "backend_asset/images/india1.png";
                ;
                $allSeries[] = $temp;
            }
            if (empty($allSeries)) {
                $return['status'] = 0;
                $return['message'] = 'Series not found';
            } else {
                $return['response'] = $allSeries;
                $return['status'] = 1;
                $return['message'] = 'Series found successfully';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: matches
     * Description:   To recent matches list
     */
    function matches_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $options = array(
                'table' => 'matches',
                'select' => 'prediction_code,play_status,match_id,series_id,match_date,match_time,status,'
                . 'match_type,match_num,localteam_id,localteam,'
                . 'visitorteam_id,visitorteam,status,'
                . 'IF ((SELECT count(id) from contest_matches where match_id=matches.match_id), "YES", "NO") as isContest,'
                . 'matchTeamlocal.team_flag as localteam_flag,matchTeamVisit.team_flag as visitorteam_flag',
                'join' => array('team as matchTeamlocal' => 'matchTeamlocal.id=matches.localteam_id',
                    'team as matchTeamVisit' => 'matchTeamVisit.id=matches.visitorteam_id'),
                'where' => array('matches.play_status' => 1, 'matches.delete_status' => 0),
                'where_in' => array('matches.status' => array('open', 'completed')),
                'order' => array('matches.match_date_time' => 'ASC')
            );
            $series_id = extract_value($data, 'series_id', '');
            if (!empty($series_id)) {
                $options['where']['series_id'] = $series_id;
            }
            $matches = $this->common_model->customGet($options);
            //echo $this->db->last_query();exit;
            if (!empty($matches)) {
                $matchArr = array();
                foreach ($matches as $match) {
                    $temp['match_id'] = $match->match_id;
                    $temp['series_id'] = $match->series_id;
                    $temp['match_date'] = $match->match_date;
                    $temp['match_time'] = $match->match_time;
                    $temp['status'] = $match->status;
                    $temp['match_type'] = $match->match_type;
                    $temp['match_num'] = $match->match_num;
                    $temp['localteam_id'] = $match->localteam_id;
                    $temp['localteam'] = ABRConvert($match->localteam);
                    $temp['visitorteam_id'] = $match->visitorteam_id;
                    $temp['visitorteam'] = ABRConvert($match->visitorteam);
                    $localteam_flag = base_url() . "backend_asset/images/india1.png";
                    if (!empty($match->localteam_flag)) {
                        $localteam_flag = base_url() . $match->localteam_flag;
                    }
                    $visitorteam_flag = base_url() . "backend_asset/images/india1.png";
                    if (!empty($match->visitorteam_flag)) {
                        $visitorteam_flag = base_url() . $match->visitorteam_flag;
                    }
                    $temp['localteam_image'] = $localteam_flag;
                    $temp['visitorteam_image'] = $visitorteam_flag;
                    $temp['isContest'] = $match->isContest;
                    $temp['play_status'] = $match->play_status;
                    $temp['prediction_code'] = null_checker($match->prediction_code);
                    $matchArr[] = $temp;
                }
                $return['response'] = $matchArr;
                $return['status'] = 1;
                $return['message'] = 'Matches found successfully';
            } else {
                $return['status'] = 0;
                $return['message'] = 'Matches not found';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: matche_detail
     * Description:   To get single match detail
     */
    function matche_detail_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('match_id', 'Match Id', 'trim|required|numeric');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $match_id = extract_value($data, 'match_id', '');
            $user_id = $this->user_details->id;

            $options = array(
                'table' => 'matches',
                'select' => 'prediction_code,match_date_time,play_status,match_id,series_id,match_date,match_time,status,localteam_first_inning,localteam_second_inning,visitorteam_first_inning,visitorteam_second_inning,live_score_status,'
                . 'match_type,match_num,localteam_id,localteam,'
                . 'visitorteam_id,visitorteam,status,'
                . 'IF ((SELECT count(id) from contest_matches where match_id=matches.match_id), "YES", "NO") as isContest,'
                . '(SELECT count(id) from user_team where user_team.match_id=matches.match_id AND user_id=' . $user_id . ') as myTeam,'
                . '(SELECT count(id) from join_contest where join_contest.match_id=matches.match_id AND user_id=' . $user_id . ') as myJoinedContest,'
                . 'matchTeamlocal.team_flag as localteam_flag,matchTeamVisit.team_flag as visitorteam_flag',
                'join' => array('team as matchTeamlocal' => 'matchTeamlocal.id=matches.localteam_id',
                    'team as matchTeamVisit' => 'matchTeamVisit.id=matches.visitorteam_id'),
                //'where' => array('play_status' => 1),
                'where_in' => array('status' => array('open', 'completed')),
                'single' => true
            );
            $options['where']['match_id'] = $match_id;
            $match = $this->common_model->customGet($options);
            if (!empty($match)) {
                $temp['match_id'] = null_checker($match->match_id);
                $temp['series_id'] = null_checker($match->series_id);
                $temp['match_date'] = null_checker($match->match_date);
                $temp['match_time'] = null_checker($match->match_time);
                $temp['status'] = null_checker($match->status);
                $temp['match_type'] = null_checker($match->match_type);
                $temp['match_num'] = null_checker($match->match_num);
                $temp['localteam_id'] = null_checker($match->localteam_id);
                $temp['localteam'] = ABRConvert($match->localteam);
                $temp['localteam_name'] = $match->localteam;
                $localteam_flag = base_url() . "backend_asset/images/india1.png";
                if (!empty($match->localteam_flag)) {
                    $localteam_flag = base_url() . $match->localteam_flag;
                }
                $visitorteam_flag = base_url() . "backend_asset/images/india1.png";
                if (!empty($match->visitorteam_flag)) {
                    $visitorteam_flag = base_url() . $match->visitorteam_flag;
                }
                $temp['localteam_image'] = $localteam_flag;
                $temp['visitorteam_image'] = $visitorteam_flag;
                $temp['localteam_first_inning'] = $match->localteam_first_inning;
                $temp['localteam_second_inning'] = $match->localteam_second_inning;
                $temp['visitorteam_first_inning'] = $match->visitorteam_first_inning;
                $temp['visitorteam_second_inning'] = $match->visitorteam_second_inning;
                $temp['live_score_status'] = $match->live_score_status;
                $temp['visitorteam_id'] = $match->visitorteam_id;
                $temp['visitorteam'] = ABRConvert($match->visitorteam);
                $temp['visitorteam_name'] = $match->visitorteam;
                $temp['isContest'] = $match->isContest;
                $temp['match_date_time'] = $match->match_date_time;
                $temp['match_status'] = "FIXTURE";
                $temp['currentTime'] = date('Y-m-d H:i:s');
                $temp['play_status'] = $match->play_status;
                $temp['prediction_code'] = null_checker($match->prediction_code);
                $status = $match->status;
                $UtcDateTime = trim(ISTToConvertUTC(date('Y-m-d H:i'), 'UTC', 'UTC'));
                // $UtcDateTime = explode(' ', $UtcDateTime);
                $match_date_time = $match->match_date_time;
                $new_match_date_time = explode(' ', $match_date_time);
                if ($status == "open") {
                    if (!empty($UtcDateTime)) {
                        $match_date = $UtcDateTime[0];

                        $match_time = $UtcDateTime[1];
                        $new_match_date = $new_match_date_time[0];
                        if ($match_date_time <= $UtcDateTime) {

                            $temp['match_status'] = "LIVE";
                        }
                    }
                } else if ($status == "completed") {
                    $temp['match_status'] = "COMPLETED";
                }
                $options = array(
                    'table' => 'contest as ct',
                    'select' => 'ct.id,ct.match_type,ct.contest_name,ct.total_winning_amount,ct.contest_size,ct.chip,'
                    . 'ct.team_entry_fee,ct.number_of_winners,ct.is_multientry,ct.confirm_contest,ct.mega_contest,ct.user_invite_code',
                    'join' => array('contest_matches as cm' => 'cm.contest_id=ct.id'),
                    'where' => array('cm.match_id' => $match_id, 'ct.mega_contest' => 1),
                    'single' => true
                );
                $constant = $this->common_model->customGet($options);
                $contestResponse = new stdClass();
                if (!empty($constant)) {
                    $temp1['id'] = null_checker($constant->id);
                    $temp1['match_type'] = null_checker($constant->match_type);
                    $temp1['contest_name'] = null_checker($constant->contest_name);
                    $temp1['total_winning_amount'] = null_checker($constant->total_winning_amount);
                    $temp1['contest_size'] = null_checker($constant->contest_size);
                    $temp1['team_entry_fee'] = null_checker($constant->team_entry_fee);
                    $temp1['chip'] = null_checker($constant->chip);
                    $temp1['number_of_winners'] = null_checker($constant->number_of_winners);
                    $temp1['is_multientry'] = null_checker($constant->is_multientry);
                    $temp1['confirm_contest'] = null_checker($constant->confirm_contest);
                    $temp1['mega_contest'] = null_checker($constant->mega_contest);
                    $temp1['contest_join_code'] = null_checker($constant->user_invite_code);
                    $options = array(
                        'table' => 'join_contest',
                        'select' => 'id',
                        'where' => array('contest_id' => $constant->id,
                            'user_id' => $user_id,
                            'match_id' => $match_id)
                    );
                    $isJoined = $this->common_model->customGet($options);
                    $temp1['is_user_joined'] = (!empty($isJoined)) ? 1 : 0;
                    $options = array(
                        'table' => 'contest_details',
                        'select' => 'from_winner,to_winner,amount',
                        'where' => array('contest_id' => $constant->id)
                    );
                    $rank = $this->common_model->customGet($options);
                    $temp1['winners_rank'] = array();
                    if (!empty($rank)) {
                        foreach ($rank as $rows) {
                            $temp2['rank'] = ($rows->from_winner == $rows->to_winner) ? $rows->from_winner : $rows->from_winner . ' - ' . $rows->to_winner;
                            $temp2['prize'] = $rows->amount;
                            $temp1['winners_rank'][] = $temp2;
                        }
                    }
                    $temp1['total_join_team'] = 0;
                    $sql = "SELECT `join_contest`.`id`
                            FROM `join_contest`
                            WHERE `contest_id` = $constant->id
                            ";
                    $joinedTeam = $this->common_model->customQueryCount($sql);
                    $temp1['total_join_team'] = $joinedTeam;
                    $contestResponse = $temp1;
                    $return['mega_contest'] = $contestResponse;
                    $return['is_mega_contest'] = 1;
                } else {
                    $return['is_mega_contest'] = 0;
                }
                $return['response'] = $temp;
                $return['my_team'] = $match->myTeam;
                $return['my_joined_contest'] = $match->myJoinedContest;
                $return['status'] = 1;
                $return['message'] = 'Match found successfully';
            } else {
                $return['status'] = 0;
                $return['message'] = 'Match not found';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: matches_list
     * Description:   To recent matches list fixture, live, complete
     */
    function matches_list_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('type', 'Type', 'trim|required|in_list[FIXTURE,LIVE,COMPLETED]');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $type = extract_value($data, 'type', '');
            $limit = extract_value($data, 'limit', '');
            $offset = extract_value($data, 'offset', '');
            $prevDate = date('Y-m-d', strtotime('-1 day', strtotime(date('Y-m-d'))));
            $currDate = date('Y-m-d');
            $options = array(
                'table' => 'matches',
                'select' => 'match_date_time,play_status,match_id,series_id,match_date,match_time,status,'
                . 'match_type,match_num,localteam_id,localteam,'
                . 'visitorteam_id,visitorteam,status,'
                . 'IF ((SELECT count(id) from contest_matches where match_id=matches.match_id), "YES", "NO") as isContest,'
                . 'matchTeamlocal.team_flag as localteam_flag,matchTeamVisit.team_flag as visitorteam_flag',
                'join' => array('team as matchTeamlocal' => 'matchTeamlocal.id=matches.localteam_id',
                    'team as matchTeamVisit' => 'matchTeamVisit.id=matches.visitorteam_id'),
                'where' => array('status' => 'open', 'matches.play_status' => 1, 'matches.delete_status' => 0),
                'limit' => array($limit => $offset),
                'order' => array('match_date' => 'ASC', 'match_time' => 'ASC')
            );
            $options_count = array(
                'table' => 'matches',
                'select' => 'play_status,match_id,series_id,match_date,match_time,status,'
                . 'match_type,match_num,localteam_id,localteam,'
                . 'visitorteam_id,visitorteam,status,'
                . 'IF ((SELECT count(id) from contest_matches where match_id=matches.match_id), "YES", "NO") as isContest',
                'where' => array('status' => 'open', 'matches.play_status' => 1, 'matches.delete_status' => 0)
            );
            if ($type == "LIVE") {
                $UtcDateTime1 = trim(ISTToConvertUTC(date('Y-m-d H:i'), 'UTC', 'UTC'));
                $UtcDateTime = explode(' ', $UtcDateTime1);
                if (!empty($UtcDateTime)) {
                    /* $options['where']['match_date'] = $UtcDateTime[0];
                      $options['where']['match_time <='] = $UtcDateTime[1];
                      $options_count['where']['match_date'] = $UtcDateTime[0];
                      $options_count['where']['match_time <='] = $UtcDateTime[1]; */
                    $options['where']['match_date_time <='] = $UtcDateTime1;
                    $options_count['where']['match_date_time <='] = $UtcDateTime1;
                    $options['where']['status'] = 'open';
                    $options_count['where']['status'] = 'open';
                }
            }
            if ($type == "FIXTURE") {
                $UtcDateTime1 = trim(ISTToConvertUTC(date('Y-m-d H:i'), 'UTC', 'UTC'));
                $UtcDateTime = explode(' ', $UtcDateTime1);
                if (!empty($UtcDateTime)) {
                    /* $options['where']['match_time >'] = $UtcDateTime[1];
                      $options_count['where']['match_date >='] = $UtcDateTime[0];
                      $options_count['where']['match_time >'] = $UtcDateTime[1]; */
                    $options['where']['match_date_time >='] = $UtcDateTime1;
                    $options_count['where']['match_date_time >='] = $UtcDateTime1;
                    $options['where']['status'] = 'open';
                    $options_count['where']['status'] = 'open';
                }
            }
            if ($type == "COMPLETED") {
                $options['order']['match_date'] = 'DESC';
                $options['where']['status'] = 'completed';
                $options_count['where']['status'] = 'completed';
            }
            $total_matches = $this->common_model->customCount($options_count);
            $matches = $this->common_model->customGet($options);
            if (!empty($matches)) {
                $matchArr = array();
                foreach ($matches as $match) {
                    $temp['match_id'] = $match->match_id;
                    $temp['series_id'] = $match->series_id;
                    $temp['match_date'] = $match->match_date;
                    $temp['match_time'] = $match->match_time;
                    $temp['status'] = $match->status;
                    $temp['match_type'] = $match->match_type;
                    $temp['match_num'] = $match->match_num;
                    $temp['localteam_id'] = $match->localteam_id;
                    $temp['localteam'] = ABRConvert($match->localteam);
                    $temp['visitorteam_id'] = $match->visitorteam_id;
                    $temp['visitorteam'] = ABRConvert($match->visitorteam);
                    $localteam_flag = base_url() . "backend_asset/images/india1.png";
                    if (!empty($match->localteam_flag)) {
                        $localteam_flag = base_url() . $match->localteam_flag;
                    }
                    $visitorteam_flag = base_url() . "backend_asset/images/india1.png";
                    if (!empty($match->visitorteam_flag)) {
                        $visitorteam_flag = base_url() . $match->visitorteam_flag;
                    }
                    $temp['localteam_image'] = $localteam_flag;
                    $temp['visitorteam_image'] = $visitorteam_flag;
                    $temp['isContest'] = $match->isContest;
                    $temp['play_status'] = $match->play_status;
                    $temp['match_date_time'] = $match->match_date_time;
                    $temp['currentTime'] = date('Y-m-d H:i:s');
                    $matchArr[] = $temp;
                }
                $return['total_matches'] = $total_matches;
                $return['response'] = $matchArr;
                $return['status'] = 1;
                $return['message'] = 'Matches found successfully';
            } else {
                $return['status'] = 0;
                $return['message'] = 'Matches not found';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: matches_list
     * Description:   To recent matches list fixture, live, complete
     */
    function ipl_matches_list_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();

        $this->form_validation->set_rules('login_session', 'Login session', 'required');


        //$this->form_validation->set_rules('type', 'Type', 'trim|required|in_list[FIXTURE,LIVE,COMPLETED]');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $type = extract_value($data, 'type', '');
            $limit = extract_value($data, 'limit', '');
            $offset = extract_value($data, 'offset', '');
            $login_session_key = "dream-team-cricket-fantasy11-com";
            $prevDate = date('Y-m-d', strtotime('-1 day', strtotime(date('Y-m-d'))));
            $currDate = date('Y-m-d');
            $options = array(
                'table' => 'matches',
                'select' => 'match_date_time,play_status,match_id,series_id,match_date,match_time,status,'
                . 'match_type,match_num,localteam_id,localteam,'
                . 'visitorteam_id,visitorteam,status,'
                . 'IF ((SELECT count(id) from contest_matches where match_id=matches.match_id), "YES", "NO") as isContest,'
                . 'matchTeamlocal.team_flag as localteam_flag,matchTeamVisit.team_flag as visitorteam_flag',
                'join' => array('team as matchTeamlocal' => 'matchTeamlocal.id=matches.localteam_id',
                    'team as matchTeamVisit' => 'matchTeamVisit.id=matches.visitorteam_id'),
                'where' => array('status' => 'open', 'series_id' => 1015),
                // 'limit' => array($limit => $offset),
                'order' => array('match_date' => 'ASC', 'match_time' => 'ASC')
            );
            $options_count = array(
                'table' => 'matches',
                'select' => 'play_status,match_id,series_id,match_date,match_time,status,'
                . 'match_type,match_num,localteam_id,localteam,'
                . 'visitorteam_id,visitorteam,status,'
                . 'IF ((SELECT count(id) from contest_matches where match_id=matches.match_id), "YES", "NO") as isContest',
                'where' => array('status' => 'open', 'series_id' => 1015)
            );
            // if ($type == "LIVE") {
            //     $UtcDateTime1 = trim(ISTToConvertUTC(date('Y-m-d H:i'), 'UTC', 'UTC'));
            //     $UtcDateTime = explode(' ', $UtcDateTime1);
            //     if (!empty($UtcDateTime)) {
            //        /* $options['where']['match_date'] = $UtcDateTime[0];
            //         $options['where']['match_time <='] = $UtcDateTime[1];
            //         $options_count['where']['match_date'] = $UtcDateTime[0];
            //         $options_count['where']['match_time <='] = $UtcDateTime[1];*/
            //         $options['where']['match_date_time <='] = $UtcDateTime1;
            //         $options_count['where']['match_date_time <='] = $UtcDateTime1;
            //         $options['where']['status'] = 'open';
            //         $options_count['where']['status'] = 'open';
            //     }
            // }
            // if ($type == "FIXTURE") {
            //     $UtcDateTime1 = trim(ISTToConvertUTC(date('Y-m-d H:i'), 'UTC', 'UTC'));
            //     $UtcDateTime = explode(' ', $UtcDateTime1);
            //     if (!empty($UtcDateTime)) {
            //         /*$options['where']['match_time >'] = $UtcDateTime[1];
            //         $options_count['where']['match_date >='] = $UtcDateTime[0];
            //         $options_count['where']['match_time >'] = $UtcDateTime[1];*/
            //         $options['where']['match_date_time >='] = $UtcDateTime1;
            //         $options_count['where']['match_date_time >='] = $UtcDateTime1;
            //         $options['where']['status'] = 'open';
            //         $options_count['where']['status'] = 'open';
            //     }
            // }
            // if ($type == "COMPLETED") {
            //     $options['where']['status'] = 'completed';
            //     $options_count['where']['status'] = 'completed';
            // }
            $total_matches = $this->common_model->customCount($options_count);
            $matches = $this->common_model->customGet($options);
            if (!empty($matches)) {
                $matchArr = array();
                foreach ($matches as $match) {
                    $temp['match_id'] = $match->match_id;
                    $temp['series_id'] = $match->series_id;
                    $temp['match_date'] = $match->match_date;
                    $temp['match_time'] = $match->match_time;
                    $temp['status'] = $match->status;
                    $temp['match_type'] = $match->match_type;
                    $temp['match_num'] = $match->match_num;
                    $temp['localteam_id'] = $match->localteam_id;
                    $temp['localteam'] = ABRConvert($match->localteam);
                    $temp['visitorteam_id'] = $match->visitorteam_id;
                    $temp['visitorteam'] = ABRConvert($match->visitorteam);
                    $localteam_flag = base_url() . "backend_asset/images/india1.png";
                    if (!empty($match->localteam_flag)) {
                        $localteam_flag = base_url() . $match->localteam_flag;
                    }
                    $visitorteam_flag = base_url() . "backend_asset/images/india1.png";
                    if (!empty($match->visitorteam_flag)) {
                        $visitorteam_flag = base_url() . $match->visitorteam_flag;
                    }
                    $temp['localteam_image'] = $localteam_flag;
                    $temp['visitorteam_image'] = $visitorteam_flag;
                    $temp['isContest'] = $match->isContest;
                    $temp['play_status'] = $match->play_status;
                    $temp['match_date_time'] = $match->match_date_time;
                    $temp['currentTime'] = date('Y-m-d H:i:s');
                    $matchArr[] = $temp;
                }
                $return['total_matches'] = $total_matches;
                $return['response'] = $matchArr;
                $return['status'] = 1;
                $return['message'] = 'Matches found successfully';
            } else {
                $return['status'] = 0;
                $return['message'] = 'Matches not found';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: match_player
     * Description:   To get match palyers
     */
    function match_player_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('series_id', 'Series Id', 'trim|required|numeric');
        $this->form_validation->set_rules('localteam_id', 'Localteam Id', 'trim|required|numeric');
        $this->form_validation->set_rules('visitorteam_id', 'Visitorteam Id', 'trim|required|numeric');
        $this->form_validation->set_rules('match_id', 'Match Id', 'trim|required|numeric');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $dataArr = array();
            $series_id = extract_value($data, 'series_id', '');
            $localteam_id = extract_value($data, 'localteam_id', '');
            $visitorteam_id = extract_value($data, 'visitorteam_id', '');
            $matchId = extract_value($data, 'match_id', '');
            $options = array(
                'table' => 'matches',
                'select' => 'match_type',
                'where' => array('match_id' => $matchId),
                'single' => true
            );
            $matchData = $this->common_model->customGet($options);
            /* $option = array(
              'table' => 'match_player',
              'select' => 'match_player.*',
              'where' => array('match_player.series_id' => $series_id),
              'where_in' => array('team_id' => array($localteam_id, $visitorteam_id))
              );
              if (!empty($matchData)) {
              $match_type = strtolower($matchData->match_type);
              if (strpos($match_type, 'odi') !== false) {
              $option['where']['match_player.odi'] = 1;
              }
              if (strpos($match_type, 'test') !== false) {
              $option['where']['match_player.test'] = 1;
              }
              if (strpos($match_type, 'twenty20') !== false) {
              $option['where']['match_player.t20'] = 1;
              }
              if (strpos($match_type, 't20i') !== false) {
              $option['where']['match_player.t20'] = 1;
              }
              } */
            $select = "MP.odi_credit_points as points";
            if (!empty($matchData)) {
                $matchType = strtolower($matchData->match_type);
                if (strpos($matchType, 'odi') !== false) {
                    $select = "MP.odi_credit_points as points";
                } else if (strpos($matchType, 'twenty20') !== false) {
                    $select = "MP.t20_credit_points as points";
                } else if (strpos($matchType, 't20i') !== false) {
                    $select = "MP.t20_credit_points as points";
                } else if (strpos($matchType, 'first-class') !== false) {
                    $select = "MP.first_class_credit_points as points";
                } else if (strpos($matchType, 'list') !== false) {
                    $select = "MP.list_a_credit_points as points";
                } else if (strpos($matchType, 'test') !== false) {
                    $select = "MP.test_credit_points as points";
                }
                $players = array();
                $sql = "SELECT MP.series_id,MP.team_id,MP.team,MP.player_id,MP.player_name,MP.play_role,
                    player_pic,$select
                    FROM match_player as MP 
                    INNER JOIN player_details as PD ON PD.player_id = MP.player_id
                    WHERE MP.series_id = $series_id";
                if (strpos($matchType, 'odi') !== false) {
                    //$sql .= " AND MP.odi = 1";
                } else if (strpos($matchType, 'test') !== false) {
                    //$sql .= " AND MP.test = 1";
                } else if (strpos($matchType, 't20i') !== false) {
                    //$sql .= " AND MP. t20 = 1";
                }
                $sql .= " AND MP.team_id IN($localteam_id, $visitorteam_id) GROUP BY MP.player_id ORDER BY points DESC";
                $matchPlayer = $this->common_model->customQuery($sql);
                if (!empty($matchPlayer)) {
                    foreach ($matchPlayer as $player) {
                        $temp['series_id'] = $player->series_id;
                        $temp['team_id'] = $player->team_id;
                        $temp['team'] = ABRConvert($player->team);
                        $temp['team_name'] = $player->team;
                        $temp['player_id'] = $player->player_id;
                        $temp['name'] = $player->player_name;
                        $temp['play_role'] = $player->play_role;
                        $temp['points'] = $player->points;
                        $playerPic = $player->player_pic;
                        if (!empty($playerPic)) {
                            $playerPic = base_url() . $player->player_pic;
                        } else {
                            $icon = "";
                            if ($player->play_role == "BATSMAN") {
                                $icon = "batsmen2.png";
                            } else if ($player->play_role == "ALLROUNDER") {
                                $icon = "all_rounder2.png";
                            } else if ($player->play_role == "WICKETKEEPER") {
                                $icon = "keeper2.png";
                            } else if ($player->play_role == "BOWLER") {
                                $icon = "bowler2.png";
                            }
                            $playerPic = base_url() . 'backend_asset/player_icon/' . $icon;
                        }
                        $temp['player_pic'] = $playerPic;
                        $players[] = $temp;
                    }
                    $return['response'] = $players;
                    $return['status'] = 1;
                    $return['message'] = 'Players found successfully';
                } else {
                    $return['status'] = 0;
                    $return['message'] = 'Players not found';
                }
            } else {
                $return['status'] = 0;
                $return['message'] = 'Match not found';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: create_team
     * Description:   To create team
     */
    function create_team_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('user_id', 'User Id', 'trim|required|numeric');
        $this->form_validation->set_rules('match_id', 'Match Id', 'trim|required|numeric');
        $this->form_validation->set_rules('player_id', 'Player Id', 'trim|required');
        $this->form_validation->set_rules('series_id', 'Series Id', 'trim|required');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {

            $UtcDateTime1 = trim(ISTToConvertUTC(date('Y-m-d H:i'), 'UTC', 'UTC'));
            $options = array(
                'table' => 'matches',
                'select' => 'match_id,match_date_time',
                'where' => array('status' => 'open',
                    'matches.play_status' => 1,
                    'match_id' => extract_value($data, 'match_id', '')),
                'single' => true
            );
            $matches_details = $this->common_model->customGet($options);
            if (!empty($matches_details)) {
                $match_date_time = strtotime($matches_details->match_date_time) - 3600;
                $currentDateTime = strtotime($UtcDateTime1);
                // if ($currentDateTime >= $match_date_time) {
                //     $return['status'] = 0;
                //     $return['message'] = "Time is over you can't create team";
                //     $this->response($return);
                //     exit;
                // }
            } else {
                $return['status'] = 0;
                $return['message'] = 'Match not found';
                $this->response($return);
                exit;
            }

            $dataArr = array();
            $user_id = extract_value($data, 'user_id', '');
            $match_id = extract_value($data, 'match_id', '');
            $player_id = extract_value($data, 'player_id', '');
            $series_id = extract_value($data, 'series_id', '');
            $players = json_decode($player_id);
            $allPlayers = json_decode(extract_value($data, 'player_id', ''));
            $playerId = array();
            $teamId = array();
            $position = array();
            $playerRole = array();
            if (is_array($players)) {
                if (count($players) == 11) {
                    foreach ($players as $play) {
                        $playerId[] = $play->player_id;
                        $teamId[] = $play->team_id;
                        $position[] = $play->position;
                        $playerRole[] = $play->play_role;
                    }
                    /* To validate total team player in single team */
                    $totalTeamPlayer = array_count_values($teamId);
                    if (count($totalTeamPlayer) == 2) {
                        foreach ($totalTeamPlayer as $team) {
                            if ($team > 7) {
                                $return['status'] = 0;
                                $return['message'] = 'Max 7 players from 1 team';
                                $this->response($return);
                                exit;
                            }
                        }
                    }
                    /* To validate duplicate player */
                    $totalPlayer = array_count_values($playerId);
                    if (in_array(2, $totalPlayer)) {
                        $return['status'] = 0;
                        $return['message'] = 'Duplicate player can not valid';
                        $this->response($return);
                        exit;
                    }
                    /* To validate WICKETKEEPER */
                    $totalPlayingRole = array_count_values($playerRole);
                    if (!isset($totalPlayingRole['WICKETKEEPER'])) {
                        $return['status'] = 0;
                        $return['message'] = 'You have to pick 1 wicket keeper';
                        $this->response($return);
                        exit;
                    } else if ($totalPlayingRole['WICKETKEEPER'] > 1) {
                        $return['status'] = 0;
                        $return['message'] = 'You can only pick 1 wicket keeper';
                        $this->response($return);
                        exit;
                    }
                    /* To validate BATSMAN */
                    if (!isset($totalPlayingRole['BATSMAN'])) {
                        $return['status'] = 0;
                        $return['message'] = 'You have to pick Min 3 & Max 6 Batsman';
                        $this->response($return);
                        exit;
                    } else if ($totalPlayingRole['BATSMAN'] < 3) {
                        $return['status'] = 0;
                        $return['message'] = 'Every team needs atleast 3 Batsmen';
                        $this->response($return);
                        exit;
                    } else if ($totalPlayingRole['BATSMAN'] > 6) {
                        $return['status'] = 0;
                        $return['message'] = 'Max 6 batsmen allowed';
                        $this->response($return);
                        exit;
                    }
                    /* To validate BOWLER */
                    if (!isset($totalPlayingRole['BOWLER'])) {
                        $return['status'] = 0;
                        $return['message'] = 'You have to pick Min 3 & Max 6 Bowler';
                        $this->response($return);
                        exit;
                    } else if ($totalPlayingRole['BOWLER'] < 3) {
                        $return['status'] = 0;
                        $return['message'] = 'Every team needs atleast 3 Bowler';
                        $this->response($return);
                        exit;
                    } else if ($totalPlayingRole['BOWLER'] > 6) {
                        $return['status'] = 0;
                        $return['message'] = 'Max 6 Bowler allowed';
                        $this->response($return);
                        exit;
                    }
                    /* To validate ALLROUNDER */
                    if (isset($totalPlayingRole['ALLROUNDER'])) {
                        if ($totalPlayingRole['ALLROUNDER'] > 4) {
                            $return['status'] = 0;
                            $return['message'] = 'Max 4 all-rounder allowed';
                            $this->response($return);
                            exit;
                        }
                    }
                    /* if (!isset($totalPlayingRole['ALLROUNDER'])) {
                      $return['status'] = 0;
                      $return['message'] = 'You have to pick Min 1 & Max 3 all-rounder';
                      $this->response($return);
                      exit;
                      } else if ($totalPlayingRole['ALLROUNDER'] < 1) {
                      $return['status'] = 0;
                      $return['message'] = 'Every team needs atleast 1 all-rounder';
                      $this->response($return);
                      exit;
                      } else if ($totalPlayingRole['ALLROUNDER'] > 4) {
                      $return['status'] = 0;
                      $return['message'] = 'Max 4 all-rounder allowed';
                      $this->response($return);
                      exit;
                      } */
                    /* To validate check play role */
                    if (isset($totalPlayingRole[""])) {
                        $return['status'] = 0;
                        $return['message'] = 'Player play role invalid';
                        $this->response($return);
                        exit;
                    }
                    /* To validate check CAPTAIN */
                    $totalPosition = array_count_values($position);
                    if (!isset($totalPosition["CAPTAIN"])) {
                        $return['status'] = 0;
                        $return['message'] = 'You have to select 1 Captain';
                        $this->response($return);
                        exit;
                    }
                    /* To validate check VICE_CAPTAIN */
                    $totalPosition = array_count_values($position);
                    if (!isset($totalPosition["VICE_CAPTAIN"])) {
                        $return['status'] = 0;
                        $return['message'] = 'You have to select 1 Vice-Captain';
                        $this->response($return);
                        exit;
                    }
                    foreach ($allPlayers as $key => $ply) {
                        if ($ply->position == "PLAYER") {
                            unset($allPlayers[$key]);
                        } else {
                            
                        }
                    }
                    $flag = false;
                    foreach ($allPlayers as $ply) {
                        $options = array(
                            'table' => 'user_team',
                            'select' => 'user_team.id as team_id,team_player.player_position,team_player.player_id',
                            'join' => array('user_team_player as team_player' => 'team_player.user_team_id=user_team.id'),
                            'where' => array(
                                'team_player.team_id' => $ply->team_id,
                                'user_team.user_id' => $user_id,
                                'user_team.match_id' => $match_id,
                                'team_player.player_id' => $ply->player_id,
                                'team_player.player_position' => $ply->position,
                            )
                        );
                        $existsTeam = $this->common_model->customGet($options);
                        if (empty($existsTeam)) {
                            $flag = true;
                        }
                    }
                    if ($flag) {
                        $options = array(
                            'table' => 'user_team',
                            'select' => 'name',
                            'where' => array(
                                'user_id' => $user_id,
                                'match_id' => $match_id
                            ),
                            'single' => true,
                            'order' => array('id' => 'desc')
                        );
                        $teamName = $this->common_model->customGet($options);
                        $name = "";
                        if (!empty($teamName)) {
                            $oldName = explode(" ", trim($teamName->name));
                            $number = (isset($oldName[1])) ? (int) $oldName[1] + 1 : 1;
                            $name = "Team " . $number;
                        } else {
                            $name = "Team 1";
                        }
                        $options = array(
                            'table' => 'user_team',
                            'data' => array(
                                'name' => $name,
                                'user_id' => $user_id,
                                'match_id' => $match_id,
                                'series_id' => $series_id,
                                'create_date' => date('Y-m-d H:i:s')
                            )
                        );
                        $team_id = $this->common_model->customInsert($options);
                        if (!empty($team_id)) {
                            foreach ($players as $player) {
                                $options = array(
                                    'table' => 'user_team_player',
                                    'data' => array(
                                        'user_team_id' => $team_id,
                                        'player_id' => $player->player_id,
                                        'player_position' => $player->position,
                                        'team_id' => $player->team_id
                                    )
                                );
                                $this->common_model->customInsert($options);
                            }
                        } else {
                            $return['status'] = 0;
                            $return['message'] = 'Error in team create';
                        }
                        $return['team_id'] = $team_id;
                        $return['status'] = 1;
                        $return['message'] = 'Successfully team created';
                    } else {
                        $return['status'] = 0;
                        $return['message'] = "You've already created this team. Change your Playing (XI) and/or Captain & Vice-Captain";
                    }
                } else {
                    $return['status'] = 0;
                    $return['message'] = 'Player list cannot be less than (XI) or greater than (XI)';
                }
            } else {
                $return['status'] = 0;
                $return['message'] = 'Error in team create';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: edit_team
     * Description:   To edit team
     */
    function edit_team_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('user_id', 'User Id', 'trim|required|numeric');
        $this->form_validation->set_rules('match_id', 'Match Id', 'trim|required|numeric');
        $this->form_validation->set_rules('user_team_id', 'User Team Id', 'trim|required|numeric');
        $this->form_validation->set_rules('player_id', 'Player Id', 'trim|required');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $UtcDateTime1 = trim(ISTToConvertUTC(date('Y-m-d H:i'), 'UTC', 'UTC'));
            $options = array(
                'table' => 'matches',
                'select' => 'match_id,match_date_time',
                'where' => array('status' => 'open',
                    'matches.play_status' => 1,
                    'match_id' => extract_value($data, 'match_id', '')),
                'single' => true
            );
            $matches_details = $this->common_model->customGet($options);
            if (!empty($matches_details)) {
                // $match_date_time = strtotime($matches_details->match_date_time) - 3600;
                $match_date_time = strtotime($matches_details->match_date_time);
                $currentDateTime = strtotime($UtcDateTime1);
                /* if ($currentDateTime >= $match_date_time) {
                  $return['status'] = 0;
                  $return['message'] = "Time is over you can't change team";
                  $this->response($return);
                  exit;
                  } */

                if ($currentDateTime >= $match_date_time) {
                    $return['status'] = 0;
                    $return['message'] = "Time is over you can't change team";
                    $this->response($return);
                    exit;
                }
            } else {
                $return['status'] = 0;
                $return['message'] = 'Match not found';
                $this->response($return);
                exit;
            }
            $dataArr = array();
            $user_id = extract_value($data, 'user_id', '');
            $match_id = extract_value($data, 'match_id', '');
            $player_id = extract_value($data, 'player_id', '');
            $team_id = extract_value($data, 'user_team_id', '');
            $player_id = json_decode($player_id);
            $playerId = array();
            $teamId = array();
            $position = array();
            $playerRole = array();
            if (is_array($player_id)) {
                if (count($player_id) == 11) {
                    $options = array(
                        'table' => 'user_team',
                        'where' => array(
                            'id' => $team_id,
                        )
                    );
                    $isTeamId = $this->common_model->customGet($options);
                    if (empty($isTeamId)) {
                        $return['status'] = 0;
                        $return['message'] = 'Team Id invalid';
                        $this->response($return);
                        exit;
                    }
                    foreach ($player_id as $play) {
                        $playerId[] = $play->player_id;
                        $teamId[] = $play->team_id;
                        $position[] = $play->position;
                        $playerRole[] = $play->play_role;
                    }
                    /* To validate total team player in single team */
                    $totalTeamPlayer = array_count_values($teamId);
                    if (count($totalTeamPlayer) == 2) {
                        foreach ($totalTeamPlayer as $team) {
                            if ($team > 7) {
                                $return['status'] = 0;
                                $return['message'] = 'Max 7 players from 1 team';
                                $this->response($return);
                                exit;
                            }
                        }
                    }
                    /* To validate duplicate player */
                    $totalPlayer = array_count_values($playerId);
                    if (in_array(2, $totalPlayer)) {
                        $return['status'] = 0;
                        $return['message'] = 'Duplicate player can not valid';
                        $this->response($return);
                        exit;
                    }
                    $totalPlayingRole = array_count_values($playerRole);
                    /* To validate check play role */
                    if (isset($totalPlayingRole[""])) {
                        $return['status'] = 0;
                        $return['message'] = 'Player play role invalid';
                        $this->response($return);
                        exit;
                    }
                    /* To validate WICKETKEEPER */
                    if (!isset($totalPlayingRole['WICKETKEEPER'])) {
                        $return['status'] = 0;
                        $return['message'] = 'You have to pick 1 wicket keeper';
                        $this->response($return);
                        exit;
                    } else if ($totalPlayingRole['WICKETKEEPER'] > 1) {
                        $return['status'] = 0;
                        $return['message'] = 'You can only pick 1 wicket keeper';
                        $this->response($return);
                        exit;
                    }
                    /* To validate BATSMAN */
                    if (!isset($totalPlayingRole['BATSMAN'])) {
                        $return['status'] = 0;
                        $return['message'] = 'You have to pick Min 3 & Max 5 Batsman';
                        $this->response($return);
                        exit;
                    } else if ($totalPlayingRole['BATSMAN'] < 3) {
                        $return['status'] = 0;
                        $return['message'] = 'Every team needs atleast 3 Batsmen';
                        $this->response($return);
                        exit;
                    } else if ($totalPlayingRole['BATSMAN'] > 5) {
                        $return['status'] = 0;
                        $return['message'] = 'Max 5 batsmen allowed';
                        $this->response($return);
                        exit;
                    }
                    /* To validate BOWLER */
                    if (!isset($totalPlayingRole['BOWLER'])) {
                        $return['status'] = 0;
                        $return['message'] = 'You have to pick Min 3 & Max 5 Bowler';
                        $this->response($return);
                        exit;
                    } else if ($totalPlayingRole['BOWLER'] < 3) {
                        $return['status'] = 0;
                        $return['message'] = 'Every team needs atleast 3 Bowler';
                        $this->response($return);
                        exit;
                    } else if ($totalPlayingRole['BOWLER'] > 5) {
                        $return['status'] = 0;
                        $return['message'] = 'Max 5 Bowler allowed';
                        $this->response($return);
                        exit;
                    }
                    /* To validate ALLROUNDER */
                    if (!isset($totalPlayingRole['ALLROUNDER'])) {
                        $return['status'] = 0;
                        $return['message'] = 'You have to pick Min 1 & Max 3 all-rounder';
                        $this->response($return);
                        exit;
                    } else if ($totalPlayingRole['ALLROUNDER'] < 1) {
                        $return['status'] = 0;
                        $return['message'] = 'Every team needs atleast 1 all-rounder';
                        $this->response($return);
                        exit;
                    } else if ($totalPlayingRole['ALLROUNDER'] > 3) {
                        $return['status'] = 0;
                        $return['message'] = 'Max 3 all-rounder allowed';
                        $this->response($return);
                        exit;
                    }
                    /* To validate check CAPTAIN */
                    $totalPosition = array_count_values($position);
                    if (!isset($totalPosition["CAPTAIN"])) {
                        $return['status'] = 0;
                        $return['message'] = 'You have to select 1 Captain';
                        $this->response($return);
                        exit;
                    }
                    /* To validate check VICE_CAPTAIN */
                    $totalPosition = array_count_values($position);
                    if (!isset($totalPosition["VICE_CAPTAIN"])) {
                        $return['status'] = 0;
                        $return['message'] = 'You have to select 1 Vice-Captain';
                        $this->response($return);
                        exit;
                    }
                    $options = array(
                        'table' => 'user_team_player',
                        'where' => array(
                            'user_team_id' => $team_id,
                        )
                    );
                    $this->common_model->customDelete($options);
                    foreach ($player_id as $player) {
                        $options = array(
                            'table' => 'user_team_player',
                            'data' => array(
                                'user_team_id' => $team_id,
                                'player_id' => $player->player_id,
                                'player_position' => $player->position,
                                'team_id' => $player->team_id
                            )
                        );
                        $this->common_model->customInsert($options);
                    }
                    $return['status'] = 1;
                    $return['message'] = 'Successfully team updated';
                } else {
                    $return['status'] = 0;
                    $return['message'] = 'Player list cannot be less than (XI) or greater than (XI)';
                }
            } else {
                $return['status'] = 0;
                $return['message'] = 'Error in team update';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: player_details
     * Description:   To get player details
     */
    function player_details_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('player_id', 'Player Id', 'trim|required|numeric');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $player_id = extract_value($data, 'player_id', '');
            /* $options = array(
              'table' => 'player_details',
              'select' => 'player_details.*,IFNULL(match_player.odi_credit_points,0) as points',
              'join' => array(array('match_player', 'match_player.player_id=player_details.player_id', 'left')),
              'where' => array('player_details.player_id' => $player_id),
              'single' => true
              ); */
            $sql = "SELECT `player_details`.*, IFNULL(match_player.odi_credit_points, 0) as `points`
                    FROM `player_details`
                    LEFT JOIN `match_player` ON `match_player`.`player_id`=`player_details`.`player_id`
                    WHERE `player_details`.`player_id` = $player_id GROUP BY `player_details`.`player_id`";
            $playerDetails = $this->common_model->customQuery($sql, TRUE);
            if (!empty($playerDetails)) {
                $response = array();
                $batting_fielding_data = json_decode($playerDetails->batting_fielding_data);
                $bowling_data = json_decode($playerDetails->bowling_data);
                $playerDetails->batting_fielding_data = $batting_fielding_data;
                $playerDetails->bowling_data = $bowling_data;
                if (!empty($playerDetails->player_pic)) {
                    $player_pic = base_url() . $playerDetails->player_pic;
                } else {
                    $player_pic = base_url() . 'backend_asset/images/cricket-player.png';
                }
                $playerDetails->player_pic = $player_pic;
                $return['response'] = $playerDetails;
                $return['status'] = 1;
                $return['message'] = 'Player details found successfully';
            } else {

                $return['status'] = 0;
                $return['message'] = 'Player details not found';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: user_team_list_post
     * Description:   To get user team list
     */
    function user_team_list_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('user_id', 'User Id', 'trim|required|numeric');
        $this->form_validation->set_rules('match_id', 'Match Id', 'trim|required|numeric');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $dataArr = array();
            $user_id = extract_value($data, 'user_id', '');
            $match_id = extract_value($data, 'match_id', '');
            $options = array(
                'table' => 'matches',
                'select' => 'series_id,match_type',
                'where' => array(
                    'match_id' => $match_id
                ),
                'single' => true
            );
            $series = $this->common_model->customGet($options);
            $options = array(
                'table' => 'user_team',
                'select' => 'user_team.name,user_team.id as user_team_id',
                'where' => array(
                    'user_id' => $user_id,
                    'match_id' => $match_id
                )
            );
            $teamList = $this->common_model->customGet($options);
            if (!empty($teamList)) {
                $select = "match_player.odi_credit_points as points";
                $matchType = strtolower($series->match_type);
                if (strpos($matchType, 'odi') !== false) {
                    $select = "match_player.odi_credit_points as points";
                } else if (strpos($matchType, 'twenty20') !== false) {
                    $select = "match_player.t20_credit_points as points";
                } else if (strpos($matchType, 't20i') !== false) {
                    $select = "match_player.t20_credit_points as points";
                } else if (strpos($matchType, 'first-class') !== false) {
                    $select = "match_player.first_class_credit_points as points";
                } else if (strpos($matchType, 'list') !== false) {
                    $select = "match_player.list_a_credit_points as points";
                } else if (strpos($matchType, 'test') !== false) {
                    $select = "match_player.test_credit_points as points";
                }
                foreach ($teamList as $key => $team) {
                    $options = array(
                        'table' => 'user_team_player as team_player',
                        'select' => '' . $select . ',team_player.player_id,team_player.player_position as position,match_player.player_name as name,'
                        . ',player_detail.player_pic,match_player.team,match_player.series_id,'
                        . 'team_player.team_id,match_player.play_role',
                        'join' => array(array('match_player as match_player', 'match_player.player_id=team_player.player_id', 'inner'),
                            array('player_details as player_detail', 'player_detail.player_id=team_player.player_id', 'left')),
                        'where' => array(
                            'team_player.user_team_id' => $team->user_team_id,
                            'match_player.series_id' => $series->series_id
                        ),
                        'group_by' => array('team_player.player_id')
                    );
                    $teamPlayer = $this->common_model->customGet($options);
                    $teamPlayerList = array();
                    if (!empty($teamPlayer)) {
                        foreach ($teamPlayer as $player) {
                            $temp['player_id'] = $player->player_id;
                            $temp['position'] = $player->position;
                            $temp['team_id'] = $player->team_id;
                            $temp['series_id'] = $player->series_id;
                            $temp['team'] = ABRConvert($player->team);
                            $temp['team_name'] = $player->team;
                            $temp['name'] = $player->name;
                            $temp['play_role'] = $player->play_role;
                            $temp['points'] = $player->points;
                            $playerPic = $player->player_pic;
                            if (!empty($playerPic)) {
                                $playerPic = base_url() . $player->player_pic;
                            } else {
                                if ($player->play_role == "BATSMAN") {
                                    $icon = "batsmen2.png";
                                } else if ($player->play_role == "ALLROUNDER") {
                                    $icon = "all_rounder2.png";
                                } else if ($player->play_role == "WICKETKEEPER") {
                                    $icon = "keeper2.png";
                                } else if ($player->play_role == "BOWLER") {
                                    $icon = "bowler2.png";
                                }
                                $playerPic = base_url() . 'backend_asset/player_icon/' . $icon;
                            }
                            $temp['player_pic'] = $playerPic;
                            $teamPlayerList[] = $temp;
                        }
                    }
                    $teamList[$key]->players = $teamPlayerList;
                }
                $return['response'] = $teamList;
                $return['status'] = 1;
                $return['message'] = 'Team successfully found';
            } else {
                $return['status'] = 0;
                $return['message'] = 'Team not found';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: team_details_post
     * Description:   To get single team details
     */
    function team_details_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('user_team_id', 'User Team Id', 'trim|required|numeric');
        $this->form_validation->set_rules('match_id', 'Match Id', 'trim|required|numeric');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $dataArr = array();
            $team_id = extract_value($data, 'user_team_id', '');
            $match_id = extract_value($data, 'match_id', '');
            $options = array(
                'table' => 'user_team',
                'select' => 'user_team.name,user_team.id as team_id,user_team.series_id',
                'where' => array(
                    'id' => $team_id,
                    'match_id' => $match_id
                ),
                'single' => true
            );
            $teamList = $this->common_model->customGet($options);
            if (!empty($teamList)) {
                $options = array(
                    'table' => 'matches',
                    'select' => 'localteam_id,visitorteam_id',
                    'where' => array(
                        'match_id' => $match_id
                    ),
                    'single' => true
                );
                $matchDetails = $this->common_model->customGet($options);
                $select = "match_player.odi_credit_points as points";
                if (!empty($matchDetails)) {
                    $options = array(
                        'table' => 'matches',
                        'select' => 'match_type',
                        'where' => array(
                            'match_id' => $match_id
                        ),
                        'single' => true
                    );
                    $matchData = $this->common_model->customGet($options);
                    $matchType = strtolower($matchData->match_type);
                    if (strpos($matchType, 'odi') !== false) {
                        $select = "match_player.odi_credit_points as points";
                    } else if (strpos($matchType, 'twenty20') !== false) {
                        $select = "match_player.t20_credit_points as points";
                    } else if (strpos($matchType, 't20i') !== false) {
                        $select = "match_player.t20_credit_points as points";
                    } else if (strpos($matchType, 'first-class') !== false) {
                        $select = "match_player.first_class_credit_points as points";
                    } else if (strpos($matchType, 'list') !== false) {
                        $select = "match_player.list_a_credit_points as points";
                    } else if (strpos($matchType, 'test') !== false) {
                        $select = "match_player.test_credit_points as points";
                    }
                    $options = array(
                        'table' => 'user_team_player as team_player',
                        'select' => 'team_player.player_id,team_player.player_position as position,match_player.team_id,match_player.series_id,match_player.team,match_player.player_name as name,match_player.play_role,match_player.test,match_player.odi,match_player.t20,'
                        . ',player_detail.player_pic,' . $select . '',
                        'join' => array(array('match_player as match_player', 'match_player.player_id=team_player.player_id', 'inner'),
                            array('player_details as player_detail', 'player_detail.player_id=team_player.player_id', 'left')),
                        'where' => array(
                            'team_player.user_team_id' => $teamList->team_id,
                            'match_player.series_id' => $teamList->series_id
                        ),
                        'where_in' => array('match_player.team_id' => (array) $matchDetails)
                    );
                    $teamPlayer = $this->common_model->customGet($options);
                    $teamPlayerList = array();
                    if (!empty($teamPlayer)) {
                        foreach ($teamPlayer as $player) {
                            $temp['player_id'] = $player->player_id;
                            $temp['position'] = $player->position;
                            $temp['team_id'] = $player->team_id;
                            $temp['series_id'] = $player->series_id;
                            $temp['team'] = ABRConvert($player->team);
                            $temp['team_name'] = $player->team;
                            $temp['name'] = $player->name;
                            $temp['play_role'] = $player->play_role;
                            $temp['points'] = $player->points;
                            $playerPic = $player->player_pic;
                            if (!empty($playerPic)) {
                                $playerPic = base_url() . $player->player_pic;
                            } else {
                                if ($player->play_role == "BATSMAN") {
                                    $icon = "batsmen2.png";
                                } else if ($player->play_role == "ALLROUNDER") {
                                    $icon = "all_rounder2.png";
                                } else if ($player->play_role == "WICKETKEEPER") {
                                    $icon = "keeper2.png";
                                } else if ($player->play_role == "BOWLER") {
                                    $icon = "bowler2.png";
                                }
                                $playerPic = base_url() . 'backend_asset/player_icon/' . $icon;
                            }
                            $temp['player_pic'] = $playerPic;
                            $teamPlayerList[] = $temp;
                        }
                    }

                    $teamList->players = $teamPlayerList;
                    $return['response'] = $teamList;
                    $return['status'] = 1;
                    $return['message'] = 'Team successfully found';
                } else {
                    $return['status'] = 0;
                    $return['message'] = 'Match not found';
                }
            } else {
                $return['status'] = 0;
                $return['message'] = 'Team not found';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: team_details_post
     * Description:   To get single team details
     */
    function players_score_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('user_team_id', 'User Team Id', 'trim|required|numeric');
        $this->form_validation->set_rules('match_id', 'Match Id', 'trim|required|numeric');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $dataArr = array();
            $team_id = extract_value($data, 'user_team_id', '');
            $match_id = extract_value($data, 'match_id', '');
            $options = array(
                'table' => 'user_team as team',
                'select' => 'team_player.player_id',
                'join' =>
                array('user_team_player as team_player' => 'team_player.user_team_id=team.id'),
                'where' => array('team_player.user_team_id' => $team_id,
                    'team.match_id' => $match_id)
            );
            $teamPlayerAll = $this->common_model->customGet($options);
            $playerID = array();
            foreach ($teamPlayerAll as $rows) {
                $playerID[] = $rows->player_id;
            }
            $options = array(
                'table' => 'user_team',
                'select' => 'user_team.name,user_team.id as team_id,user_team.series_id',
                'where' => array(
                    'id' => $team_id,
                    'match_id' => $match_id
                ),
                'single' => true
            );
            $teamList = $this->common_model->customGet($options);
            if (!empty($teamList)) {
                $options = array(
                    'table' => 'matches',
                    'select' => 'localteam_id,visitorteam_id',
                    'where' => array(
                        'match_id' => $match_id
                    ),
                    'single' => true
                );
                $matchDetails = $this->common_model->customGet($options);
                $select = "match_player.odi_credit_points as points";
                if (!empty($matchDetails)) {
                    $options = array(
                        'table' => 'matches',
                        'select' => 'match_type',
                        'where' => array(
                            'match_id' => $match_id
                        ),
                        'single' => true
                    );
                    $matchData = $this->common_model->customGet($options);
                    $matchType = strtolower($matchData->match_type);
                    if (strpos($matchType, 'odi') !== false) {
                        $select = "match_player.odi_credit_points as points";
                    } else if (strpos($matchType, 'twenty20') !== false) {
                        $select = "match_player.t20_credit_points as points";
                    } else if (strpos($matchType, 't20i') !== false) {
                        $select = "match_player.t20_credit_points as points";
                    } else if (strpos($matchType, 'first-class') !== false) {
                        $select = "match_player.first_class_credit_points as points";
                    } else if (strpos($matchType, 'list') !== false) {
                        $select = "match_player.list_a_credit_points as points";
                    } else if (strpos($matchType, 'test') !== false) {
                        $select = "match_player.test_credit_points as points";
                    }
                    $options = array(
                        'table' => 'user_team_player as team_player',
                        'select' => 'team_player.player_id,team_player.player_position as position,match_player.team_id,match_player.series_id,match_player.team,match_player.player_name as name,match_player.play_role,match_player.test,match_player.odi,match_player.t20,'
                        . ',player_detail.player_pic,points.total_point as points',
                        'join' => array(array('match_player as match_player', 'match_player.player_id=team_player.player_id', 'inner'),
                            array('player_details as player_detail', 'player_detail.player_id=team_player.player_id', 'left'),
                            array('match_player_points as points', 'points.player_id=team_player.player_id', 'left')),
                        'where' => array(
                            'team_player.user_team_id' => $teamList->team_id,
                            'match_player.series_id' => $teamList->series_id,
                            'points.match_id' => $match_id
                        ),
                        'where_in' => array('match_player.team_id' => (array) $matchDetails),
                        'group_by' => 'team_player.player_id'
                    );
                    $teamPlayer = $this->common_model->customGet($options);
                    $teamPlayerList = array();
                    $playPlayerID = array();
                    if (!empty($teamPlayer)) {
                        foreach ($teamPlayer as $player) {
                            if (!empty($player->points)) {
                                $points = $player->points;
                            } else {
                                $points = 0;
                            }
                            $playPlayerID[] = $player->player_id;
                            $temp['player_id'] = $player->player_id;
                            $temp['position'] = $playerPosition = $player->position;
                            $temp['team_id'] = $player->team_id;
                            $temp['series_id'] = $player->series_id;
                            $temp['team'] = ABRConvert($player->team);
                            $temp['team_name'] = $player->team;
                            $temp['name'] = $player->name;
                            $temp['play_role'] = $player->play_role;
                            $temp['points'] = $points;
                            if ($playerPosition == "CAPTAIN") {
                                $temp['points'] = $points * 2;
                            }
                            if ($playerPosition == "VICE_CAPTAIN") {
                                $temp['points'] = $points + $points * .5;
                            }
                            $playerPic = $player->player_pic;
                            if (!empty($playerPic)) {
                                $playerPic = base_url() . $player->player_pic;
                            } else {
                                if ($player->play_role == "BATSMAN") {
                                    $icon = "batsmen2.png";
                                } else if ($player->play_role == "ALLROUNDER") {
                                    $icon = "all_rounder2.png";
                                } else if ($player->play_role == "WICKETKEEPER") {
                                    $icon = "keeper2.png";
                                } else if ($player->play_role == "BOWLER") {
                                    $icon = "bowler2.png";
                                }
                                $playerPic = base_url() . 'backend_asset/player_icon/' . $icon;
                            }
                            $temp['player_pic'] = $playerPic;
                            $teamPlayerList[] = $temp;
                        }
                    }
                    $outOfteamPlayer = array_diff($playerID, $playPlayerID);
                    if (!empty($outOfteamPlayer)) {
                        foreach ($outOfteamPlayer as $rows) {
                            $base_url = base_url();
                            $in = $matchDetails->localteam_id . ',' . $matchDetails->visitorteam_id;
                            $sql = "SELECT team_player.player_id, team_player.player_position as position, match_player.team_id, match_player.series_id, match_player.team, match_player.player_name as name, match_player.play_role, match_player.test, match_player.odi, match_player.t20, CONCAT('" . $base_url . "', player_detail.player_pic) as player_pic, 0 as points
                        FROM user_team_player as team_player
                        INNER JOIN match_player as match_player ON match_player.player_id=team_player.player_id
                        LEFT JOIN player_details as player_detail ON player_detail.player_id=team_player.player_id
                        WHERE team_player.user_team_id = " . $teamList->team_id . "
                        AND match_player.series_id = " . $teamList->series_id . "
                        AND team_player.player_id = " . $rows . "
                        AND match_player.team_id IN(" . $in . ")
                        GROUP BY team_player.player_id";
                            $teamPlayerList[] = $this->common_model->customQuery($sql, true);
                        }
                    }
                    $teamList->players = $teamPlayerList;
                    $return['response'] = $teamList;
                    $return['status'] = 1;
                    $return['message'] = 'Team successfully found';
                } else {
                    $return['status'] = 0;
                    $return['message'] = 'Match not found';
                }
            } else {
                $return['status'] = 0;
                $return['message'] = 'Team not found';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: contests_matches_list
     * Description:   To contest joined match list
     */
    function contests_matches_list_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('type', 'Type', 'trim|required|in_list[FIXTURE,LIVE,COMPLETED]');

        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $type = extract_value($data, 'type', '');
            $limit = extract_value($data, 'limit', '');
            $offset = extract_value($data, 'offset', '');

            $options = array(
                'table' => 'matches',
                'select' => 'matches.match_date_time,play_status,matches.match_id,matches.series_id,matches.match_date,matches.match_time,matches.status,'
                . 'matches.match_type,matches.match_num,matches.localteam_id,matches.localteam,'
                . 'matches.visitorteam_id,matches.visitorteam,matches.status,'
                . 'IF ((SELECT count(id) from contest_matches where match_id=matches.match_id), "YES", "NO") as isContest,'
                . 'matchTeamlocal.team_flag as localteam_flag,matchTeamVisit.team_flag as visitorteam_flag',
                'join' => array('team as matchTeamlocal' => 'matchTeamlocal.id=matches.localteam_id',
                    'team as matchTeamVisit' => 'matchTeamVisit.id=matches.visitorteam_id',
                    'join_contest as JC' => 'JC.match_id=matches.match_id'),
                'where' => array('play_status' => 1, 'status' => 'open', 'JC.user_id' => $this->user_details->id),
                'limit' => array($limit => $offset),
                'order' => array('match_date' => 'ASC', 'match_time' => 'ASC'),
                'group_by' => 'matches.match_id'
            );



            $options_count = array(
                'table' => 'matches',
                'select' => 'JC.user_id,JC.contest_id,matches.match_id,matches.series_id,matches.match_date,matches.match_time,matches.status,'
                . 'matches.match_type,matches.match_num,matches.localteam_id,matches.localteam,'
                . 'matches.visitorteam_id,matches.visitorteam,matches.status,'
                . 'IF ((SELECT count(id) from contest_matches where match_id=matches.match_id), "YES", "NO") as isContest,'
                . 'matchTeamlocal.team_flag as localteam_flag,matchTeamVisit.team_flag as visitorteam_flag',
                'join' => array('team as matchTeamlocal' => 'matchTeamlocal.id=matches.localteam_id',
                    'team as matchTeamVisit' => 'matchTeamVisit.id=matches.visitorteam_id',
                    'join_contest as JC' => 'JC.match_id=matches.match_id'
                ),
                'where' => array('play_status' => 1, 'status' => 'open', 'JC.user_id' => $this->user_details->id),
                'group_by' => 'matches.match_id'
            );
            if ($type == "LIVE") {
                $UtcDateTime1 = trim(ISTToConvertUTC(date('Y-m-d H:i'), 'UTC', 'UTC'));
                $UtcDateTime = explode(' ', $UtcDateTime1);
                if (!empty($UtcDateTime)) {
                    /* $options['where']['match_date'] = $UtcDateTime[0];
                      $options['where']['match_time <='] = $UtcDateTime[1];
                      $options_count['where']['match_date'] = $UtcDateTime[0];
                      $options_count['where']['match_time <='] = $UtcDateTime[1]; */
                    $options['where']['match_date_time <='] = $UtcDateTime1;
                    $options_count['where']['match_date_time <='] = $UtcDateTime1;
                    $options['where']['status'] = 'open';
                    $options_count['where']['status'] = 'open';
                }
            }
            if ($type == "FIXTURE") {
                $UtcDateTime1 = trim(ISTToConvertUTC(date('Y-m-d H:i'), 'UTC', 'UTC'));
                $UtcDateTime = explode(' ', $UtcDateTime1);
                if (!empty($UtcDateTime)) {
                    /* $options['where']['match_time >'] = $UtcDateTime[1];
                      $options_count['where']['match_date >='] = $UtcDateTime[0];
                      $options_count['where']['match_time >'] = $UtcDateTime[1]; */
                    $options['where']['match_date_time >='] = $UtcDateTime1;
                    $options_count['where']['match_date_time >='] = $UtcDateTime1;
                    $options['where']['status'] = 'open';
                    $options_count['where']['status'] = 'open';
                }
            }
            if ($type == "COMPLETED") {
                $options['order']['match_date'] = 'DESC';
                $options['where']['status'] = 'completed';
                $options_count['where']['status'] = 'completed';
            }

            $total_matches = $this->common_model->customCount($options_count);
            $matches = $this->common_model->customGet($options);
            //echo $this->db->last_query();exit;
            if (!empty($matches)) {
                $matchArr = array();
                foreach ($matches as $match) {
                    $temp['match_id'] = $match->match_id;
                    $temp['series_id'] = $match->series_id;
                    $temp['match_date'] = $match->match_date;
                    $temp['match_time'] = $match->match_time;
                    $temp['status'] = $match->status;
                    $temp['match_type'] = $match->match_type;
                    $temp['match_num'] = $match->match_num;
                    $temp['localteam_id'] = $match->localteam_id;
                    $temp['localteam'] = ABRConvert($match->localteam);
                    $temp['visitorteam_id'] = $match->visitorteam_id;
                    $temp['visitorteam'] = ABRConvert($match->visitorteam);
                    $localteam_flag = base_url() . "backend_asset/images/india1.png";
                    if (!empty($match->localteam_flag)) {
                        $localteam_flag = base_url() . $match->localteam_flag;
                    }
                    $visitorteam_flag = base_url() . "backend_asset/images/india1.png";
                    if (!empty($match->visitorteam_flag)) {
                        $visitorteam_flag = base_url() . $match->visitorteam_flag;
                    }
                    $temp['localteam_image'] = $localteam_flag;
                    $temp['visitorteam_image'] = $visitorteam_flag;
                    $temp['isContest'] = 'YES'; //$match->isContest;

                    $options = array(
                        'table' => 'join_contest',
                        'select' => 'id',
                        'where' => array('match_id' => $match->match_id, 'user_id' => $this->user_details->id)
                    );
                    $matchTotalJoinedContest = $this->common_model->customCount($options);
                    $temp['totalJoinedContest'] = $matchTotalJoinedContest;
                    $temp['match_date_time'] = $match->match_date_time;
                    $temp['currentTime'] = date('Y-m-d H:i:s');
                    $matchArr[] = $temp;
                }
                $return['total_matches'] = $total_matches;
                $return['response'] = $matchArr;
                $return['status'] = 1;
                $return['message'] = 'Matches found successfully';
            } else {
                $return['status'] = 0;
                $return['message'] = 'Matches not found';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: match_prediction
     * Description:   match prediction list
     */
    function match_prediction_post() {
        $return['code'] = 200;
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('user_id', 'User Id', 'trim|required');
        $this->form_validation->set_rules('match_id', 'Match Id', 'trim|required');

        $this->form_validation->set_rules('total_run_scored_by_opening_team', 'Total Run scored by opening team', 'required|trim|numeric');
        $this->form_validation->set_rules('win_by_wicket', 'Team win by how many wickets', 'required|trim|numeric');
        $this->form_validation->set_rules('win_by_runs', 'Team win by how many runs', 'required|trim|numeric');
        $this->form_validation->set_rules('maximum_six_by_plyer', 'Maximum six by which batsman', 'required|trim|numeric');
        $this->form_validation->set_rules('maximum_four_by_player', 'Maximum Four by which batsman', 'required|trim|numeric');
        $this->form_validation->set_rules('maximum_wicket_by_plyer', 'Maximum wickets by which baller', 'required|trim|numeric');
        $this->form_validation->set_rules('total_clean_bold_wicket', 'Total clean bold wickets for the day', 'required|trim|numeric');
        $this->form_validation->set_rules('man_of_the_match', 'Man of the Match', 'required|trim|numeric');
        $this->form_validation->set_rules('total_number_of_catch_out_for_the_day', 'Total Number of catch out for the day', 'required|trim|numeric');
        $this->form_validation->set_rules('winner_team_of_the_day', 'Winner Team of the day -Team List', 'required|trim|numeric');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $dataArr = array();
            $user_id = extract_value($data, 'user_id', '');
            $match_id = extract_value($data, 'match_id', '');
            $post = $this->input->post();
            $matchId = $this->input->post('match_id');

            $option = array(
                'table' => 'join_contest',
                'select' => 'id',
                'where' => array('match_id' => $match_id, 'user_id' => $user_id),
                'single' => true
            );
            $joinContestIsCheck = $this->common_model->customGet($option);
            if (empty($joinContestIsCheck)) {
                $return['status'] = 0;
                $return['message'] = "You haven't joined any contest yet, please join any contest to submit your pre predictions";
                $this->response($return);
                exit;
            }
            $option = array(
                'table' => 'matches',
                'select' => '*',
                'where' => array('match_id' => $match_id)
            );
            $UtcDateTime1 = trim(ISTToConvertUTC(date('Y-m-d H:i'), 'UTC', 'UTC'));
            $UtcDateTime = explode(' ', $UtcDateTime1);
            if (!empty($UtcDateTime)) {
                $option['where']['match_date_time >'] = $UtcDateTime1;
            }
            $match = $this->common_model->customGet($option);
            if (!empty($match)) {
                $options = array('table' => 'prediction_user_submission',
                    'where' => array('user_id' => $user_id, 'match_id' => $matchId));
                $existsKey = $this->common_model->customGet($options);
                if (empty($existsKey)) {
                    $questionKeyList = predictionQuestion();
                    foreach ($questionKeyList as $key => $value) {
                        $question_value = $this->input->post($key);
                        $options = array('table' => 'prediction_user_submission',
                            'data' => array('question_key' => $key,
                                'question_value' => $question_value,
                                'match_id' => $matchId,
                                'user_id' => $user_id,
                                'create_date' => date('Y-m-d H:i:s')
                        ));
                        $affiliate_scheme = $this->common_model->customInsert($options);
                    }
                    if ($affiliate_scheme) {
                        $return['status'] = 1;
                        $return['message'] = 'Congrats Prediction Submitted Successfully, Please get back after Match is over to check your results. Thanks you';
                    }
                } else {
                    $return['status'] = 0;
                    $return['message'] = 'Prediction has been already submitted,Please get back after Match is over to check your results';
                }
            } else {
                $return['status'] = 0;
                $return['message'] = 'Match live prediction submit before match live';
            }
        }

        $this->response($return);
    }

    /**
     * Function Name: match_prediction_submission_check
     * Description:   match player prediction check
     */
    function match_prediction_submission_check_post() {
        $return['code'] = 200;
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('user_id', 'User Id', 'trim|required');
        $this->form_validation->set_rules('match_id', 'Match Id', 'trim|required');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $dataArr = array();
            $user_id = extract_value($data, 'user_id', '');
            $match_id = extract_value($data, 'match_id', '');
            $post = $this->input->post();
            $matchId = $this->input->post('match_id');
            $option = array(
                'table' => 'join_contest',
                'select' => 'id',
                'where' => array('match_id' => $match_id, 'user_id' => $user_id),
                'single' => true
            );
            $joinContestIsCheck = $this->common_model->customGet($option);
            if (empty($joinContestIsCheck)) {
                $return['status'] = 0;
                $return['message'] = "You haven't joined any contest yet, please join any contest to submit your pre predictions";
                $this->response($return);
                exit;
            }
            $option = array(
                'table' => 'matches',
                'select' => '*',
                'where' => array('match_id' => $match_id)
            );
            $UtcDateTime1 = trim(ISTToConvertUTC(date('Y-m-d H:i'), 'UTC', 'UTC'));
            $UtcDateTime = explode(' ', $UtcDateTime1);
            if (!empty($UtcDateTime)) {
                $option['where']['match_date_time >'] = $UtcDateTime1;
            }
            $match = $this->common_model->customGet($option);
            if (!empty($match)) {
                $options = array('table' => 'prediction_user_submission',
                    'where' => array('user_id' => $user_id, 'match_id' => $matchId));
                $existsKey = $this->common_model->customGet($options);
                if (!empty($existsKey)) {
                    $return['status'] = 0;
                    $return['message'] = 'Prediction has been already submitted,Please get back after Match is over to check your results';
                } else {
                    $return['status'] = 1;
                }
            } else {
                $return['status'] = 0;
                $return['message'] = 'Match live prediction submit before match live';
            }
        }

        $this->response($return);
    }

    /**
     * Function Name: team_match_player
     * Description:   To get match palyers for single team
     */
    function team_match_player_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('series_id', 'Series Id', 'trim|required|numeric');
        $this->form_validation->set_rules('localteam_id', 'Localteam Id', 'trim|required|numeric');
        $this->form_validation->set_rules('visitorteam_id', 'Visitorteam Id', 'trim|required|numeric');
        $this->form_validation->set_rules('match_id', 'Match Id', 'trim|required|numeric');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $dataArr = array();
            $series_id = extract_value($data, 'series_id', '');
            $localteam_id = extract_value($data, 'localteam_id', '');
            $visitorteam_id = extract_value($data, 'visitorteam_id', '');
            $matchId = extract_value($data, 'match_id', '');
            $options = array(
                'table' => 'matches',
                'select' => 'match_type',
                'where' => array('match_id' => $matchId),
                'single' => true
            );
            $matchData = $this->common_model->customGet($options);
            $select = "MP.odi_credit_points as points";
            if (!empty($matchData)) {
                $matchType = strtolower($matchData->match_type);
                if (strpos($matchType, 'odi') !== false) {
                    $select = "MP.odi_credit_points as points";
                } else if (strpos($matchType, 'twenty20') !== false) {
                    $select = "MP.t20_credit_points as points";
                } else if (strpos($matchType, 't20i') !== false) {
                    $select = "MP.t20_credit_points as points";
                } else if (strpos($matchType, 'first-class') !== false) {
                    $select = "MP.first_class_credit_points as points";
                } else if (strpos($matchType, 'list') !== false) {
                    $select = "MP.list_a_credit_points as points";
                } else if (strpos($matchType, 'test') !== false) {
                    $select = "MP.test_credit_points as points";
                }
                $sql = "SELECT MP.series_id,MP.team_id,MP.team,MP.player_id,MP.player_name,MP.play_role,
                    player_pic,$select
                    FROM match_player as MP 
                    INNER JOIN player_details as PD ON PD.player_id = MP.player_id
                    WHERE MP.series_id = $series_id";
                if (strpos($matchType, 'odi') !== false) {
                    $sql .= " AND MP.odi = 1";
                } else if (strpos($matchType, 'test') !== false) {
                    $sql .= " AND MP.test = 1";
                } else if (strpos($matchType, 't20i') !== false) {
                    $sql .= " AND MP. t20 = 1";
                }
                $sql .= " AND MP.team_id IN($localteam_id) GROUP BY MP.player_id ORDER BY points DESC";
                $matchPlayerLocalTeam = $this->common_model->customQuery($sql);
                $sql = "SELECT MP.series_id,MP.team_id,MP.team,MP.player_id,MP.player_name,MP.play_role,
                    player_pic,$select
                    FROM match_player as MP 
                    INNER JOIN player_details as PD ON PD.player_id = MP.player_id
                    WHERE MP.series_id = $series_id";
                if (strpos($matchType, 'odi') !== false) {
                    $sql .= " AND MP.odi = 1";
                } else if (strpos($matchType, 'test') !== false) {
                    $sql .= " AND MP.test = 1";
                } else if (strpos($matchType, 't20i') !== false) {
                    $sql .= " AND MP. t20 = 1";
                }
                $sql .= " AND MP.team_id IN($visitorteam_id) GROUP BY MP.player_id ORDER BY points DESC";
                $matchPlayerVisitorTeam = $this->common_model->customQuery($sql);
                $localTeamPlayer = array();
                $visitorTeamPlayer = array();
                if (!empty($matchPlayerLocalTeam) && !empty($matchPlayerVisitorTeam)) {
                    foreach ($matchPlayerLocalTeam as $player) {
                        $temp['series_id'] = $player->series_id;
                        $temp['team_id'] = $player->team_id;
                        $temp['team'] = ABRConvert($player->team);
                        $temp['team_name'] = $player->team;
                        $temp['player_id'] = $player->player_id;
                        $temp['name'] = $player->player_name;
                        $temp['play_role'] = $player->play_role;
                        $temp['points'] = $player->points;
                        $playerPic = $player->player_pic;
                        if (!empty($playerPic)) {
                            $playerPic = base_url() . $player->player_pic;
                        } else {
                            if ($player->play_role == "BATSMAN") {
                                $icon = "batsmen2.png";
                            } else if ($player->play_role == "ALLROUNDER") {
                                $icon = "all_rounder2.png";
                            } else if ($player->play_role == "WICKETKEEPER") {
                                $icon = "keeper2.png";
                            } else if ($player->play_role == "BOWLER") {
                                $icon = "bowler2.png";
                            }
                            $playerPic = base_url() . 'backend_asset/player_icon/' . $icon;
                        }
                        $temp['player_pic'] = $playerPic;
                        $localTeamPlayer[] = $temp;
                    }
                    $temp = array();
                    foreach ($matchPlayerVisitorTeam as $player) {
                        $temp['series_id'] = $player->series_id;
                        $temp['team_id'] = $player->team_id;
                        $temp['team'] = ABRConvert($player->team);
                        $temp['team_name'] = $player->team;
                        $temp['player_id'] = $player->player_id;
                        $temp['name'] = $player->player_name;
                        $temp['play_role'] = $player->play_role;
                        $temp['points'] = $player->points;
                        $playerPic = $player->player_pic;
                        if (!empty($playerPic)) {
                            $playerPic = base_url() . $player->player_pic;
                        } else {
                            if ($player->play_role == "BATSMAN") {
                                $icon = "batsmen2.png";
                            } else if ($player->play_role == "ALLROUNDER") {
                                $icon = "all_rounder2.png";
                            } else if ($player->play_role == "WICKETKEEPER") {
                                $icon = "keeper2.png";
                            } else if ($player->play_role == "BOWLER") {
                                $icon = "bowler2.png";
                            }
                            $playerPic = base_url() . 'backend_asset/player_icon/' . $icon;
                        }
                        $temp['player_pic'] = $playerPic;
                        $visitorTeamPlayer[] = $temp;
                    }
                    $return['local_team_player'] = $localTeamPlayer;
                    $return['visitor_team_player'] = $visitorTeamPlayer;
                    $return['status'] = 1;
                    $return['message'] = 'Players found successfully';
                } else {
                    $return['status'] = 0;
                    $return['message'] = 'Players not found';
                }
            } else {
                $return['status'] = 0;
                $return['message'] = 'Match not found';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: my_prediction
     * Description:   To prediction submit
     */
    function my_prediction_post() {
        $return['code'] = 200;
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $dataArr = array();
            $user_id = $this->user_details->id;
            $option = array(
                'table' => 'prediction_user_submission as PUS',
                'select' => 'MT.match_id,MT.match_date_time,MT.localteam,MT.visitorteam,MT.status',
                'join' => array('matches as MT' => 'MT.match_id=PUS.match_id'),
                'where' => array('PUS.user_id' => $user_id),
                'group_by' => 'PUS.match_id'
            );
            $UtcDateTime1 = trim(ISTToConvertUTC(date('Y-m-d H:i'), 'UTC', 'UTC'));
            $myPrediction = $this->common_model->customGet($option);
            if (!empty($myPrediction)) {
                $reponsePrediction = array();
                foreach ($myPrediction as $rows) {
                    $temp['match_id'] = $rows->match_id;
                    $temp['match_date_time'] = $rows->match_date_time;
                    $temp['localteam'] = $rows->localteam;
                    $temp['visitorteam'] = $rows->visitorteam;
                    $temp['points'] = 0;
                    $status = $rows->status;
                    if ($rows->status != "completed") {
                        if (!empty($UtcDateTime1)) {
                            if (strtotime($rows->match_date_time) <= strtotime($UtcDateTime1)) {
                                $status = 'live';
                            }
                        }
                    }
                    $temp['status'] = $status;
                    $options = array('table' => 'prediction_score_rank',
                        'select' => 'rank,winning_chip,points',
                        'where' => array('match_id' => $rows->match_id, 'user_id' => $user_id),
                        'single' => true
                    );
                    $userRankPrize = $this->common_model->customGet($options);
                    if (!empty($userRankPrize)) {
                        $temp['points'] = (int) $userRankPrize->points;
                        ;
                        $temp['rank'] = (int) $userRankPrize->rank;
                        $temp['win_chip'] = (int) $userRankPrize->winning_chip;
                    }
                    $reponsePrediction[] = $temp;
                }
                $return['response'] = $reponsePrediction;
                $return['status'] = 1;
                $return['message'] = 'Prediction listed';
            } else {
                $return['status'] = 0;
                $return['message'] = 'Prediction not found';
            }
        }

        $this->response($return);
    }

    /**
     * Function Name: match_prediction_result
     * Description:   To prediction post announce
     */
    function match_prediction_result_post() {
        $return['code'] = 200;
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('user_id', 'User Id', 'trim|required');
        $this->form_validation->set_rules('match_id', 'Match Id', 'trim|required');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $dataArr = array();
            $user_id = extract_value($data, 'user_id', '');
            $match_id = extract_value($data, 'match_id', '');
            $options = array(
                'table' => 'matches',
                'select' => '*',
                'where' => array('match_id' => $match_id),
                'single' => true
            );
            $match = $this->common_model->customGet($options);
            $matchArr = array();
            if (!empty($match)) {
                $matchArr['match_id'] = $match->match_id;
                $matchArr['series_id'] = $match->series_id;
                $matchArr['status'] = $match->status;
                $matchArr['match_type'] = $match->match_type;
                $matchArr['match_num'] = $match->match_num;
                $matchArr['localteam_id'] = $match->localteam_id;
                $matchArr['localteam'] = ABRConvert($match->localteam);
                $matchArr['visitorteam_id'] = $match->visitorteam_id;
                $matchArr['visitorteam'] = ABRConvert($match->visitorteam);
                $localteam_flag = base_url() . "backend_asset/images/india1.png";
                if (!empty($match->localteam_flag)) {
                    $localteam_flag = base_url() . $match->localteam_flag;
                }
                $visitorteam_flag = base_url() . "backend_asset/images/india1.png";
                if (!empty($match->visitorteam_flag)) {
                    $visitorteam_flag = base_url() . $match->visitorteam_flag;
                }
                $matchArr['localteam_image'] = $localteam_flag;
                $matchArr['visitorteam_image'] = $visitorteam_flag;
                $matchArr['match_date_time'] = $match->match_date_time;
            }
            $response['match_detail'] = $matchArr;
            $options = array('table' => 'prediction_user_submission',
                'where' => array('user_id' => $user_id, 'match_id' => $match_id));
            $mySubmission = $this->common_model->customGet($options);
            if (!empty($mySubmission)) {
                $result = array();
                $commonQuestion = predictionQuestion();
                $totalPoints = 0;
                foreach ($mySubmission as $rows) {
                    $question_key = $rows->question_key;
                    $userAnswer = $rows->question_value;
                    $temp['question'] = $commonQuestion[$question_key];
                    $temp['user_answer'] = $userAnswer;
                    $temp['real_answer'] = "";
                    $temp['point'] = 0;
                    if ($question_key == "total_run_scored_by_opening_team") {
                        $realAnswer = getPredictionAnswer($rows->match_id, "total_run_scored_by_opening_team");
                        if (!empty($realAnswer)) {
                            $temp['real_answer'] = $realAnswer;
                            $userRange = $userAnswer + 10;
                            if ($userAnswer <= $realAnswer && $userRange >= $realAnswer) {
                                $temp['point'] = 1;
                                $totalPoints +=1;
                            }
                        }
                    }

                    if ($question_key == "win_by_wicket") {
                        $realAnswer = getPredictionAnswer($rows->match_id, "win_by_wicket");
                        if (!empty($realAnswer)) {
                            $temp['real_answer'] = $realAnswer;
                            if ($realAnswer == $userAnswer) {
                                $temp['point'] = 1;
                                $totalPoints +=1;
                            }
                        }
                    }

                    if ($question_key == "win_by_runs") {
                        $realAnswer = getPredictionAnswer($rows->match_id, "win_by_runs");
                        if (!empty($realAnswer)) {
                            $temp['real_answer'] = $realAnswer;
                            $userRange = $userAnswer + 5;
                            if ($userAnswer <= $realAnswer && $userRange >= $realAnswer) {
                                $temp['point'] = 1;
                                $totalPoints +=1;
                            }
                        }
                    }

                    if ($question_key == "maximum_six_by_plyer") {
                        $realAnswer = getPredictionAnswer($rows->match_id, "maximum_six_by_plyer");
                        $temp['user_answer'] = getMatchPlayerName($rows->match_id, $userAnswer);
                        if (!empty($realAnswer)) {
                            $temp['real_answer'] = getMatchPlayerName($rows->match_id, $realAnswer);
                            if ($realAnswer == $userAnswer) {
                                $temp['point'] = 1;
                                $totalPoints +=1;
                            }
                        }
                    }

                    if ($question_key == "maximum_four_by_player") {
                        $realAnswer = getPredictionAnswer($rows->match_id, "maximum_four_by_player");
                        $temp['user_answer'] = getMatchPlayerName($rows->match_id, $userAnswer);
                        if (!empty($realAnswer)) {
                            $temp['real_answer'] = getMatchPlayerName($rows->match_id, $realAnswer);
                            if ($realAnswer == $userAnswer) {
                                $temp['point'] = 1;
                                $totalPoints +=1;
                            }
                        }
                    }

                    if ($question_key == "maximum_wicket_by_plyer") {
                        $realAnswer = getPredictionAnswer($rows->match_id, "maximum_wicket_by_plyer");
                        $temp['user_answer'] = getMatchPlayerName($rows->match_id, $userAnswer);
                        if (!empty($realAnswer)) {
                            $temp['real_answer'] = getMatchPlayerName($rows->match_id, $realAnswer);
                            if ($realAnswer == $userAnswer) {
                                $temp['point'] = 1;
                                $totalPoints +=1;
                            }
                        }
                    }

                    if ($question_key == "man_of_the_match") {
                        $realAnswer = getPredictionAnswer($rows->match_id, "man_of_the_match");
                        $temp['user_answer'] = getMatchPlayerName($rows->match_id, $userAnswer);
                        if (!empty($realAnswer)) {
                            $temp['real_answer'] = getMatchPlayerName($rows->match_id, $realAnswer);
                            if ($realAnswer == $userAnswer) {
                                $temp['point'] = 1;
                                $totalPoints +=1;
                            }
                        }
                    }

                    if ($question_key == "total_clean_bold_wicket") {
                        $realAnswer = getPredictionAnswer($rows->match_id, "total_clean_bold_wicket");
                        if (!empty($realAnswer)) {
                            $temp['real_answer'] = $realAnswer;
                            if ($realAnswer == $userAnswer) {
                                $temp['point'] = 1;
                                $totalPoints +=1;
                            }
                        }
                    }

                    if ($question_key == "total_number_of_catch_out_for_the_day") {
                        $realAnswer = getPredictionAnswer($rows->match_id, "total_number_of_catch_out_for_the_day");
                        if (!empty($realAnswer)) {
                            $temp['real_answer'] = $realAnswer;
                            if ($realAnswer == $userAnswer) {
                                $temp['point'] = 1;
                                $totalPoints +=1;
                            }
                        }
                    }

                    if ($question_key == "winner_team_of_the_day") {
                        $realAnswer = getPredictionAnswer($rows->match_id, "winner_team_of_the_day");
                        $temp['user_answer'] = getMatchPlayTeamName($rows->match_id, $userAnswer);
                        if (!empty($realAnswer)) {
                            $temp['real_answer'] = getMatchPlayTeamName($rows->match_id, $realAnswer);
                            if ($realAnswer == $userAnswer) {
                                $temp['point'] = 1;
                                $totalPoints +=1;
                            }
                        }
                    }
                    $result[] = $temp;
                }

                $response['question_predict'] = $result;
                $response['total_point'] = $totalPoints;
                $response['rank'] = 0;
                $response['win_chip'] = 0;
                $options = array('table' => 'prediction_score_rank',
                    'select' => 'rank,winning_chip',
                    'where' => array('match_id' => $match_id, 'user_id' => $user_id),
                    'single' => true
                );
                $userRankPrize = $this->common_model->customGet($options);
                if (!empty($userRankPrize)) {
                    $response['rank'] = (int) $userRankPrize->rank;
                    $response['win_chip'] = (int) $userRankPrize->winning_chip;
                }
                $return['response'] = $response;
                $return['status'] = 1;
                $return['message'] = 'Prediction submission listed';
            } else {
                $return['status'] = 0;
                $return['message'] = 'Prediction submission not found';
            }
        }

        $this->response($return);
    }

    /**
     * Function Name: findKeyValuePlayers
     * Description:   common function player key value
     */
    function findKeyValuePlayers($array, $value) {
        if (is_array($array)) {
            $players = array();
            foreach ($array as $key => $rows) {
                if ($rows->play_role == $value) {
                    $players[] = $array[$key];
                }
            }
            return $players;
        }
        return false;
    }

    /**
     * Function Name: findKeyArrayDiff
     * Description:   common function different key
     */
    function findKeyArrayDiff($array, $value) {
        if (is_array($array)) {
            $players = array();
            foreach ($array as $key => $rows) {
                if ($rows->player_id == $value) {
                    return false;
                }
            }
            return true;
        }
        return true;
    }

    /**
     * Function Name: match_player
     * Description:   To get match palyers
     */
    function match_players_best_played_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('series_id', 'Series Id', 'trim|required|numeric');
        $this->form_validation->set_rules('localteam_id', 'Localteam Id', 'trim|required|numeric');
        $this->form_validation->set_rules('visitorteam_id', 'Visitorteam Id', 'trim|required|numeric');
        $this->form_validation->set_rules('match_id', 'Match Id', 'trim|required|numeric');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $dataArr = array();
            $series_id = extract_value($data, 'series_id', '');
            $localteam_id = extract_value($data, 'localteam_id', '');
            $visitorteam_id = extract_value($data, 'visitorteam_id', '');
            $matchId = extract_value($data, 'match_id', '');
            $options = array(
                'table' => 'matches',
                'select' => 'match_type',
                'where' => array('match_id' => $matchId),
                'single' => true
            );
            $matchData = $this->common_model->customGet($options);
            $select = "MP.odi_credit_points as points";
            if (!empty($matchData)) {
                $matchType = strtolower($matchData->match_type);
                if (strpos($matchType, 'odi') !== false) {
                    $select = "MP.odi_credit_points as points";
                } else if (strpos($matchType, 'twenty20') !== false) {
                    $select = "MP.t20_credit_points as points";
                } else if (strpos($matchType, 't20i') !== false) {
                    $select = "MP.t20_credit_points as points";
                } else if (strpos($matchType, 'first-class') !== false) {
                    $select = "MP.first_class_credit_points as points";
                } else if (strpos($matchType, 'list') !== false) {
                    $select = "MP.list_a_credit_points as points";
                } else if (strpos($matchType, 'test') !== false) {
                    $select = "MP.test_credit_points as points";
                }
                $players = array();

                $sql = "SELECT MPP.total_point as totalPoints,MP.series_id,MP.team_id,MP.team,MP.player_id,MP.player_name,MP.play_role,
                    player_pic,$select
                    FROM match_player as MP 
                    INNER JOIN player_details as PD ON PD.player_id = MP.player_id
                    INNER JOIN match_player_points as MPP ON MPP.player_id = MP.player_id
                    WHERE MP.series_id = $series_id AND MPP.match_id= $matchId";
                if (strpos($matchType, 'odi') !== false) {
                    $sql .= " AND MP.odi = 1";
                } else if (strpos($matchType, 'test') !== false) {
                    $sql .= " AND MP.test = 1";
                } else if (strpos($matchType, 't20i') !== false) {
                    $sql .= " AND MP. t20 = 1";
                }
                $sql .= " AND MP.team_id IN($localteam_id, $visitorteam_id) GROUP BY MP.player_id";
                $matchPlayer = $this->common_model->customQuery($sql);
                //dump($matchPlayer);
                $wicketkipper = $this->findKeyValuePlayers($matchPlayer, "WICKETKEEPER");
                $batsman = $this->findKeyValuePlayers($matchPlayer, "BATSMAN");
                $bowler = $this->findKeyValuePlayers($matchPlayer, "BOWLER");
                $allrounder = $this->findKeyValuePlayers($matchPlayer, "ALLROUNDER");
                usort($batsman, function ($a, $b) {
                    return $b->totalPoints - $a->totalPoints;
                });
                $topBatsman = array_slice($batsman, 0, 3);
                usort($bowler, function ($a, $b) {
                    return $b->totalPoints - $a->totalPoints;
                });
                $topBowler = array_slice($bowler, 0, 3);
                /* usort($allrounder, function ($a, $b) {
                  return $b->totalPoints - $a->totalPoints;
                  });
                  $topAllrounder = array_slice($allrounder, 0, 2); */
                usort($wicketkipper, function ($a, $b) {
                    return $b->totalPoints - $a->totalPoints;
                });
                $topWicketkipper = array_slice($wicketkipper, 0, 1);
                $allPlayers = array();
                $allPlayers = array_merge($topBatsman, $topBowler);
                $allPlayers = array_merge($allPlayers, $topWicketkipper);
                //$allPlayers = array_merge($allPlayers, $topAllrounder);
                //dump($allPlayers);
                $unSelectPlayer = array();
                foreach ($matchPlayer as $key => $val) {
                    $flag = $this->findKeyArrayDiff($allPlayers, $val->player_id);
                    if ($flag) {
                        $unSelectPlayer[] = $matchPlayer[$key];
                    }
                }
                $tempUnselect = array();
                foreach ($unSelectPlayer as $key => $val) {
                    if ($val->play_role == "WICKETKEEPER") {
                        unset($unSelectPlayer[$key]);
                    } else {
                        $tempUnselect[] = $unSelectPlayer[$key];
                    }
                }
                $unSelectPlayer = $tempUnselect;
                $topExtraPlayer = array();
                if (!empty($unSelectPlayer)) {
                    usort($unSelectPlayer, function ($a, $b) {
                        return $b->totalPoints - $a->totalPoints;
                    });
                    $topExtraPlayer = array_slice($unSelectPlayer, 0, 4);
                }
                $allPlayers = array_merge($allPlayers, $topExtraPlayer);
                rsort($allPlayers);
                if (!empty($allPlayers)) {
                    $i = 1;
                    foreach ($allPlayers as $player) {
                        $player_position = "PLAYER";
                        if ($i == 1) {
                            $player_position = "CAPTAIN";
                        }
                        if ($i == 2) {
                            $player_position = "VICE_CAPTAIN";
                        }
                        $temp['position'] = $player_position;
                        $temp['series_id'] = $player->series_id;
                        $temp['team_id'] = $player->team_id;
                        $temp['team'] = ABRConvert($player->team);
                        $temp['team_name'] = $player->team;
                        $temp['player_id'] = $player->player_id;
                        $temp['name'] = $player->player_name;
                        $temp['play_role'] = $player->play_role;
                        $temp['points'] = $player->totalPoints;
                        $sql = "SELECT id FROM user_team WHERE user_team.match_id=$matchId";
                        $totalTeam = $this->common_model->customQueryCount($sql);
                        $sql = "SELECT team_player.`player_id`"
                                . "FROM user_team JOIN user_team_player AS team_player ON team_player.user_team_id=user_team.id "
                                . " WHERE user_team.match_id = $matchId AND team_player.player_id = $player->player_id ";
                        $totalPlayerSelect = $this->common_model->customQueryCount($sql);
                        $temp['genie_percentage'] = round((($totalPlayerSelect * 100 ) / $totalTeam), 2);
                        $playerPic = $player->player_pic;
                        if (!empty($playerPic)) {
                            $playerPic = base_url() . $player->player_pic;
                        } else {
                            if ($player->play_role == "BATSMAN") {
                                $icon = "batsmen2.png";
                            } else if ($player->play_role == "ALLROUNDER") {
                                $icon = "all_rounder2.png";
                            } else if ($player->play_role == "WICKETKEEPER") {
                                $icon = "keeper2.png";
                            } else if ($player->play_role == "BOWLER") {
                                $icon = "bowler2.png";
                            }
                            $playerPic = base_url() . 'backend_asset/player_icon/' . $icon;
                        }
                        $temp['player_pic'] = $playerPic;
                        $players[] = $temp;
                        $i++;
                    }
                    $return['response']['players'] = $players;
                    $return['status'] = 1;
                    $return['message'] = 'Best players listed';
                } else {
                    $return['status'] = 0;
                    $return['message'] = 'Players not found';
                }
            } else {
                $return['status'] = 0;
                $return['message'] = 'Match not found';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: matches_active_players
     * Description:   get active player list
     */
    function matches_active_players_post() {

        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        $this->form_validation->set_rules('series_id', 'Series Id', 'trim|required|numeric');
        //$this->form_validation->set_rules('localteam_id', 'Localteam Id', 'trim|required|numeric');
        //$this->form_validation->set_rules('visitorteam_id', 'Visitorteam Id', 'trim|required|numeric');
        $this->form_validation->set_rules('match_id', 'Match Id', 'trim|required|numeric');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {

            $dataArr = array();
            $series_id = extract_value($data, 'series_id', '');
            //$localteam_id = extract_value($data, 'localteam_id', '');
            //$visitorteam_id = extract_value($data, 'visitorteam_id', '');
            $matchId = extract_value($data, 'match_id', '');

            $sql = "SELECT user_team.match_id,team_player.player_id,team_player.player_position,player.name,player.player_pic,"
                    . "COUNT( team_player.`player_id`) AS `value_occurrence`,(SELECT COUNT(id) FROM user_team WHERE user_team.match_id=$matchId) as totalTeam "
                    . "FROM user_team JOIN user_team_player AS team_player ON team_player.user_team_id=user_team.id "
                    . "JOIN player_details AS player ON player.player_id=team_player.player_id "
                    . "WHERE team_player.player_position='CAPTAIN' AND user_team.series_id=$series_id  "
                    . "AND user_team.match_id=$matchId GROUP BY team_player.`player_id` "
                    . "ORDER BY `value_occurrence` DESC LIMIT 3";

            $captainPlayer = $this->common_model->customQuery($sql);
            $captainData = array();
            $vicecaptainData = array();
            foreach ($captainPlayer as $captain) {
                $image = "";
                if (!empty($captain->player_pic)) {
                    $image = base_url() . $captain->player_pic;
                }
                $temp['player_name'] = $captain->name;
                $temp['player_position'] = $captain->player_position;
                $temp['player_pic'] = $image;
                $temp['genie_percentage'] = round((($captain->value_occurrence * 100 ) / $captain->totalTeam), 2);

                $captainData[] = $temp;
            }


            $sql1 = "SELECT user_team.match_id,team_player.player_id,team_player.player_position,player.name,"
                    . "player.player_pic,COUNT( team_player.`player_id`) AS `value_occurrence`,(SELECT COUNT(id) FROM user_team WHERE user_team.match_id=$matchId) as totalTeam"
                    . " FROM user_team JOIN user_team_player AS team_player ON team_player.user_team_id=user_team.id "
                    . "JOIN player_details AS player ON player.player_id=team_player.player_id "
                    . "WHERE team_player.player_position='VICE_CAPTAIN' AND user_team.series_id=$series_id "
                    . "AND user_team.match_id=$matchId GROUP BY team_player.`player_id` ORDER BY `value_occurrence` "
                    . "DESC LIMIT 3";

            $vicecaptainPlayer = $this->common_model->customQuery($sql1);

            foreach ($vicecaptainPlayer as $vicecaptain) {
                $image = "";
                if (!empty($vicecaptain->player_pic)) {
                    $image = base_url() . $vicecaptain->player_pic;
                }
                $temp1['player_name'] = $vicecaptain->name;
                $temp1['player_position'] = $vicecaptain->player_position;
                $temp1['player_pic'] = $image;
                $temp1['genie_percentage'] = round((($vicecaptain->value_occurrence * 100 ) / $vicecaptain->totalTeam), 2);

                $vicecaptainData[] = $temp1;
            }
            $temp3['captaionData'] = $captainData;
            $temp3['vicecaptaionData'] = $vicecaptainData;


            $return['response'] = $temp3;
            $return['status'] = 1;
            $return['message'] = 'Best players listed';
        }

        $this->response($return);
    }

    /**
     * Function Name: matches
     * Description:   To recent matches list
     */
    function matches_live_score_post() {
        $return['code'] = 200;
        //$return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $options = array(
                'table' => 'matches',
                'select' => 'prediction_code,play_status,match_id,series_id,match_date,match_time,match_date_time,status,localteam_first_inning,localteam_second_inning,visitorteam_first_inning,visitorteam_second_inning,live_score_status,'
                . 'match_type,match_num,localteam_id,localteam,'
                . 'visitorteam_id,visitorteam,status,'
                . 'IF ((SELECT count(id) from contest_matches where match_id=matches.match_id), "YES", "NO") as isContest,'
                . 'matchTeamlocal.team_flag as localteam_flag,matchTeamVisit.team_flag as visitorteam_flag',
                'join' => array('team as matchTeamlocal' => 'matchTeamlocal.id=matches.localteam_id',
                    'team as matchTeamVisit' => 'matchTeamVisit.id=matches.visitorteam_id'),
                'where' => array('matches.play_status' => 1, 'matches.delete_status' => 0),
                //'where_in' => array('matches.status' => array('open', 'completed')),
                'order' => array('matches.match_date_time' => 'ASC')
            );
            $UtcDateTime1 = trim(ISTToConvertUTC(date('Y-m-d H:i'), 'UTC', 'UTC'));
            $UtcDateTime = explode(' ', $UtcDateTime1);
            if (!empty($UtcDateTime)) {

                $options['where']['DATE(match_date_time)'] = $UtcDateTime[0];
                $options['where']['status'] = 'open';
            }
            $matches = $this->common_model->customGet($options);
            if (!empty($matches)) {
                $matchArr = array();
                $matchDetailArr = array();
                foreach ($matches as $match) {
                    $option = array(
                        'table' => 'match_live_scoring as live_score',
                        'select' => 'live_score.match_id,live_score.player_id',
                        'where' => array('live_score.match_id' => $match->match_id),
                    );
                    $matches_details = $this->common_model->customGet($option);
                    if (!empty($matches_details)) {
                        foreach ($matches_details as $match_detail) {
                            $option = array(
                                'table' => 'match_player',
                                'select' => 'player_name,play_role,player_id',
                                'where' => array('player_id' => $match_detail->player_id, 'series_id' => $match->series_id),
                                'where_in' => array('play_role' => array('BATSMAN', 'BOWLER')),
                            );
                            $player_details = $this->common_model->customGet($option);
                            foreach ($player_details as $player_detail) {
                                $temp1['player_id'] = $player_detail->player_id;
                                $temp1['play_role'] = $player_detail->play_role;
                                $temp1['player_name'] = $player_detail->player_name;
                                $matchDetailArr[] = $temp1;
                            }
                        }
                    }
                    $temp['player_details'] = $matchDetailArr;
                    $temp['match_id'] = $match->match_id;
                    $temp['series_id'] = $match->series_id;
                    $temp['match_date'] = $match->match_date;
                    $temp['match_time'] = $match->match_time;
                    $temp['status'] = $match->status;
                    $temp['match_type'] = $match->match_type;
                    $temp['match_num'] = $match->match_num;
                    $temp['localteam_id'] = $match->localteam_id;
                    $temp['localteam'] = ABRConvert($match->localteam);
                    $temp['visitorteam_id'] = $match->visitorteam_id;
                    $temp['visitorteam'] = ABRConvert($match->visitorteam);
                    $localteam_flag = base_url() . "backend_asset/images/india1.png";
                    if (!empty($match->localteam_flag)) {
                        $localteam_flag = base_url() . $match->localteam_flag;
                    }
                    $visitorteam_flag = base_url() . "backend_asset/images/india1.png";
                    if (!empty($match->visitorteam_flag)) {
                        $visitorteam_flag = base_url() . $match->visitorteam_flag;
                    }
                    $temp['localteam_image'] = $localteam_flag;
                    $temp['visitorteam_image'] = $visitorteam_flag;
                    $temp['localteam_first_inning'] = $match->localteam_first_inning;
                    $temp['localteam_second_inning'] = $match->localteam_second_inning;
                    $temp['visitorteam_first_inning'] = $match->visitorteam_first_inning;
                    $temp['visitorteam_second_inning'] = $match->visitorteam_second_inning;
                    $temp['live_score_status'] = $match->live_score_status;
                    $temp['isContest'] = $match->isContest;
                    $temp['play_status'] = $match->play_status;
                    $temp['prediction_code'] = null_checker($match->prediction_code);
                    $matchArr[] = $temp;
                }
                if (($temp['localteam'] != "TBA") && ($temp['visitorteam'] != "TBA")) {
                    $return['response'] = $matchArr;
                    $return['status'] = 1;
                    $return['message'] = 'Matches found successfully';
                } else {
                    $return['status'] = 0;
                    $return['message'] = 'Matches not found';
                }
            } else {
                $return['status'] = 0;
                $return['message'] = 'Matches not found';
            }
        }
        $this->response($return);
    }

    /**
     * Function Name: matches
     * Description:   To recent matches list
     */
    function match_live_score_post() {
        $return['code'] = 200;
        $return['response'] = new stdClass();
        $data = $this->input->post();
        $this->form_validation->set_rules('login_session_key', 'Login session key', 'trim|required|callback__validate_login_session_key');
        // $this->form_validation->set_rules('match_id', 'Match Id', 'trim|required|numeric');
        if ($this->form_validation->run() == FALSE) {
            $error = $this->form_validation->rest_first_error_string();
            $return['status'] = 0;
            $return['message'] = $error;
        } else {
            $options = array(
                'table' => 'matches',
                'select' => 'match_id,series_id,match_date,match_time,status,'
                . 'match_type,match_num,localteam_id,localteam,match_date_time,'
                . 'visitorteam_id,visitorteam,status,localteam_first_inning,visitorteam_first_inning,live_score_status,'
                . 'matchTeamlocal.team_flag as localteam_flag,matchTeamVisit.team_flag as visitorteam_flag',
                'join' => array('team as matchTeamlocal' => 'matchTeamlocal.id=matches.localteam_id',
                    'team as matchTeamVisit' => 'matchTeamVisit.id=matches.visitorteam_id'),
                'where' => array('matches.play_status' => 1, 'matches.delete_status' => 0),
                //'where_in' => array('status' => array('completed', 'open')),
                'order' => array('match_date_time' => 'ASC')
            );
            $match_id = extract_value($data, 'match_id', '');
            // $options['where']['match_id'] = $match_id;
            $UtcDateTime1 = trim(ISTToConvertUTC(date('Y-m-d H:i'), 'UTC', 'UTC'));
            $UtcDateTime = explode(' ', $UtcDateTime1);
            if (!empty($UtcDateTime)) {

                $options['where']['DATE(match_date_time)'] = $UtcDateTime[0];
                $options['where']['status'] = 'open';
            }
            $matches = $this->common_model->customGet($options);
            //echo $this->db->last_query();die;
            if (!empty($matches)) {
                $matchArr = array();
                $battingArr = array();
                $bowlingArr = array();
                $battingVisitArr = array();
                $bowlingVisitArr = array();
                foreach ($matches as $match) {
                    $temp['match_id'] = $match->match_id;
                    $temp['series_id'] = $match->series_id;
                    $temp['match_date'] = $match->match_date;
                    $temp['match_time'] = $match->match_time;
                    $temp['status'] = $match->status;
                    $temp['match_type'] = $match->match_type;
                    $temp['localteam_first_inning'] = $match->localteam_first_inning;
                    $temp['visitorteam_first_inning'] = $match->visitorteam_first_inning;
                    $temp['live_score_status'] = $match->live_score_status;
                    $temp['match_num'] = $match->match_num;
                    $temp['localteam_id'] = $match->localteam_id;
                    $temp['localteam'] = ABRConvert($match->localteam);
                    $temp['visitorteam_id'] = $match->visitorteam_id;
                    $temp['visitorteam'] = ABRConvert($match->visitorteam);
                    $localteam_flag = base_url() . "backend_asset/images/india.png";
                    if (!empty($match->localteam_flag)) {
                        $localteam_flag = base_url() . $match->localteam_flag;
                    }
                    $visitorteam_flag = base_url() . "backend_asset/images/knighrider.png";
                    if (!empty($match->visitorteam_flag)) {
                        $visitorteam_flag = base_url() . $match->visitorteam_flag;
                    }
                    $temp['localteam_image'] = $localteam_flag;
                    $temp['visitorteam_image'] = $visitorteam_flag;
                    $options = array(
                        'table' => 'match_live_scoring',
                        'select' => 'match_live_scoring.status_lineup,match_live_scoring.batting,match_player.player_name',
                        'join' => array('match_player' => 'match_player.player_id=match_live_scoring.player_id'),
                        'where' => array('match_live_scoring.match_id' => $match->match_id,
                            'match_player.team_id' => $match->localteam_id, 'match_live_scoring.batting !=' => ""),
                        'group_by' => 'match_live_scoring.player_id',
                        'order' => array('match_live_scoring.id' => 'asc')
                    );
                    $match_player_local_batting = $this->common_model->customGet($options);

                    foreach ($match_player_local_batting as $match_player) {
                        $temp1['status_lineup'] = null_checker($match_player->status_lineup);
                        $temp1['batting'] = null_checker(json_decode($match_player->batting));
                        $temp1['player_name'] = null_checker($match_player->player_name);

                        $battingArr[] = $temp1;
                    }

                    $options = array(
                        'table' => 'match_live_scoring',
                        'select' => 'match_live_scoring.status_lineup,match_live_scoring.bowling,match_player.player_name',
                        'join' => array('match_player' => 'match_player.player_id=match_live_scoring.player_id'),
                        'where' => array('match_live_scoring.match_id' => $match->match_id,
                            'match_player.team_id' => $match->localteam_id, 'match_live_scoring.bowling !=' => ""),
                        'group_by' => 'match_live_scoring.player_id',
                        'order' => array('match_live_scoring.id' => 'asc')
                    );
                    $match_player_local_bowling = $this->common_model->customGet($options);

                    foreach ($match_player_local_bowling as $match_player_bowling) {
                        $temp2['status_lineup'] = null_checker($match_player_bowling->status_lineup);
                        $temp2['bowling'] = null_checker(json_decode($match_player_bowling->bowling));
                        $temp2['player_name'] = null_checker($match_player_bowling->player_name);

                        $bowlingArr[] = $temp2;
                    }

                    $options = array(
                        'table' => 'match_live_scoring',
                        'select' => 'match_live_scoring.status_lineup,match_live_scoring.batting,match_player.player_name',
                        'join' => array('match_player' => 'match_player.player_id=match_live_scoring.player_id'),
                        'where' => array('match_live_scoring.match_id' => $match->match_id,
                            'match_player.team_id' => $match->visitorteam_id, 'match_live_scoring.batting !=' => ""),
                        'group_by' => 'match_live_scoring.player_id',
                        'order' => array('match_live_scoring.id' => 'asc')
                    );
                    $match_player_visitor_batting = $this->common_model->customGet($options);

                    foreach ($match_player_visitor_batting as $visit_batting) {
                        $temp3['status_lineup'] = null_checker($visit_batting->status_lineup);
                        $temp3['batting'] = null_checker(json_decode($visit_batting->batting));
                        $temp3['player_name'] = null_checker($visit_batting->player_name);

                        $battingVisitArr[] = $temp3;
                    }

                    $options = array(
                        'table' => 'match_live_scoring',
                        'select' => 'match_live_scoring.status_lineup,match_live_scoring.bowling,match_player.player_name',
                        'join' => array('match_player' => 'match_player.player_id=match_live_scoring.player_id'),
                        'where' => array('match_live_scoring.match_id' => $match->match_id,
                            'match_player.team_id' => $match->visitorteam_id, 'match_live_scoring.bowling !=' => ""),
                        'group_by' => 'match_live_scoring.player_id',
                        'order' => array('match_live_scoring.id' => 'asc')
                    );
                    $match_player_visitor_bowling = $this->common_model->customGet($options);

                    foreach ($match_player_visitor_bowling as $visit_bowling) {
                        $temp4['status_lineup'] = null_checker($visit_bowling->status_lineup);
                        $temp4['bowling'] = null_checker(json_decode($visit_bowling->bowling));
                        $temp4['player_name'] = null_checker($visit_bowling->player_name);

                        $bowlingVisitArr[] = $temp4;
                    }


                    //dump($match_player_local_batting);

                    $temp['match_player_local_batting'] = $battingArr;
                    $temp['match_player_local_bowling'] = $bowlingArr;
                    $temp['match_player_visitor_batting'] = $battingVisitArr;
                    $temp['match_player_visitor_bowling'] = $bowlingVisitArr;
                    $matchArr[] = $temp;
                }
                $return['response'] = $matchArr;
                $return['status'] = 1;
                $return['message'] = 'Matches found successfully';
            } else {
                $return['status'] = 0;
                $return['message'] = 'Matches not found';
            }
        }

        $this->response($return);
    }

}

/* End of file User.php */
/* Location: ./application/controllers/api/v1/User.php */
?>
