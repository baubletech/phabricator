<?php

final class FeedPublisherHTTPWorker extends FeedPushWorker {

  protected function doWork() {
    if (PhabricatorEnv::getEnvConfig('phabricator.silent')) {
      // Don't invoke hooks in silent mode.
      return;
    }

    $story = $this->loadFeedStory();
    $data = $story->getStoryData();

    $uri = idx($this->getTaskData(), 'uri');
    $recipients = idx($this->getTaskData(), 'recipients');
    $valid_uris = PhabricatorEnv::getEnvConfig('feed.http-hooks');
    if (!in_array($uri, $valid_uris)) {
      throw new PhabricatorWorkerPermanentFailureException();
    }

    $post_data = array(
      'storyID'         => $data->getPHID(),
      'storyType'       => $data->getStoryType(),
      'storyData'       => $data->getStoryData(),
      'storyAuthorPHID' => $data->getAuthorPHID(),
      'storyText'       => $story->renderText(),
      'recipients'      => $recipients,
      'epoch'           => $data->getEpoch(),
    );

    // NOTE: We're explicitly using "http_build_query()" here because the
    // "storyData" parameter may be a nested object with arbitrary nested
    // sub-objects.
    $post_data = http_build_query($post_data, '', '&');

    id(new HTTPSFuture($uri, $post_data))
      ->setMethod('POST')
      ->setTimeout(30)
      ->resolvex();
  }

  public function getWaitBeforeRetry(PhabricatorWorkerTask $task) {
    return max($task->getFailureCount(), 1) * 60;
  }

}
