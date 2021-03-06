<?php
/**
 * @file
 * Contains \Drupal\bbb\Controller\BBBNodeTypeListController.
 */
namespace Drupal\bbb\Controller;

use Drupal\Component\Utility\Json;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

class BBBMeetingTypeController {

  /**
   * Redirect to big blue button instance; Menu callback
   *
   * @param EntityInterface $node
   *   A Drupal node Interface
   *
   * @return Drupal render array
   */
  public function attend($node) {
    if (is_numeric($node)) {
      $node = node_load($node);
    }
    $node_type = $node->getType();
    $BBBNodeTypeConfig = \Drupal::config("bbb.node_type.$node_type");
    $mode = 'attend';
    $meeting = bbb_get_meeting($node->id());
    $params = array(
      'meetingID' => $meeting->meetingID,
      'password' => $meeting->attendeePW,
    );

    $status = bbb_api_getMeetingInfo($params);
    if ($status && property_exists($status, 'hasBeenForciblyEnded') && $status->hasBeenForciblyEnded == 'true') {
      drupal_set_message('The meeting has been terminated and is not available for attending.');
      $response = new RedirectResponse(url('node/' . $node->id(), array('absolute' => TRUE)));
      return $response->send();
    }

    drupal_set_title($node->getTitle());
    if ($meeting->running) {
      if (BIGBLUEBUTTON_DISPLAY_MODE == 'blank') {
        bbb_redirect($node, $mode);
      }
    }
    else {
      if ($BBBNodeTypeConfig->get('moderatorRequired')) {
        drupal_add_js('var bbb_check_status_url = ' . Json::encode(url('node/' . $node->id() . '/meeting/status')), 'inline');
        drupal_add_js(drupal_get_path('module', 'bbb') . '/js/check_status.bbb.js');
        drupal_set_message(t('You signed up for this meeting. Please stay on this page, you will be redirected immediately after the meeting has started.'));
        return node_view($node, NULL);
      }
      else {
        if (empty($meeting->initialized)) {
          if ($data = bbb_create_meeting($node, (array) $params)) {
            // Update local data
            bbb_update_meeting($node, array_merge((array) $meeting, (array) $data));
          }
        }
        if (BIGBLUEBUTTON_DISPLAY_MODE == 'blank') {
          bbb_redirect($node, $mode);
        }
      }
    }
    $variables = array(
      'meeting' => $meeting,
      'mode' => $mode,
      'height' => BIGBLUEBUTTON_DISPLAY_HEIGHT,
      'width' => BIGBLUEBUTTON_DISPLAY_WIDTH
    );
    return theme('bbb_meeting', $variables);
  }

  /**
   * Redirect to big blue button instance; Menu callback
   *
   * @param EntityInterface $node
   *   A Drupal node Interface
   *
   * @return Drupal render array
   */
  public function moderate($node) {
    if (is_numeric($node)) {
      $node = node_load($node);
    }
    $mode = 'moderate';
    $meeting = bbb_get_meeting($node->id());

    $params = array(
      'meetingID' => $meeting->meetingID,
      'password' => $meeting->moderatorPW,
    );

    $status = bbb_api_getMeetingInfo($params);
    if ($status && property_exists($status, 'hasBeenForciblyEnded') && $status->hasBeenForciblyEnded == 'true') {
      drupal_set_message('The meeting has been terminated and is not available for reopening.');
      $response = new RedirectResponse(url('node/' . $node->id(), array('absolute' => TRUE)));
      return $response->send();
    }

    drupal_set_title($node->getTitle());
    // Implicitly create meeting
    if (empty($meeting->initialized)) {
      if ($data = bbb_create_meeting($node, (array) $params)) {
        // Update local data
        bbb_update_meeting($node, array_merge((array) $meeting, (array) $data));
      }
    }
    if (BIGBLUEBUTTON_DISPLAY_MODE == 'blank') {
      bbb_redirect($node, $mode);
    }
    $variables = array(
      'meeting' => $meeting,
      'mode' => $mode,
      'height' => BIGBLUEBUTTON_DISPLAY_HEIGHT,
      'width' => BIGBLUEBUTTON_DISPLAY_WIDTH
    );
    return theme('bbb_meeting', $variables);
  }

  /**
   * Redirect to meeting
   */
  public function redirect($node, $mode = 'attend') {
    if (is_numeric($node)) {
      $node = node_load($node);
    }
    $meeting = bbb_get_meeting($node->id(), NULL, FALSE);
    switch ($mode) {
      case 'attend':
        // Get redirect URL
        $url = parse_url($meeting->url[$mode]);
        $fullurl = $url['scheme'] . '://' . $url['host'] . (isset($url['port']) ? ':' . $url['port'] : '' ) . $url['path'] . '?' . $url['query'];
        $response = new RedirectResponse($fullurl, 301, array());
        $response->send();
        break;
      case 'moderate':
        // Get redirect URL
        $url = parse_url($meeting->url[$mode]);
        $fullurl = $url['scheme'] . '://' . $url['host'] . (isset($url['port']) ? ':' . $url['port'] : '' ) . $url['path'] . '?' . $url['query'];
        $response = new RedirectResponse($fullurl, 301, array());
        $response->send();
        break;
    }
  }

  /**
   * Return meeting status; Menu callback
   * @param $node
   *   EntityInterface node
   *
   * @return JsonResponse with boolean 'running'
   */
  public function status($node) {
    if (is_numeric($node)) {
      $node = node_load($node);
    }
    $meeting = bbb_get_meeting($node->id());
    return new JsonResponse(array('running' => $meeting->running));
  }
}
