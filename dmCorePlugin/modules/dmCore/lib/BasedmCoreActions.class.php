<?php

class BasedmCoreActions extends dmBaseActions
{
  public function executeThumbnail(dmWebRequest $request)
  {
    $tag = $this->getHelper()->£media($request->getParameter('source'));
  
    foreach(array('width', 'height', 'method', 'quality') as $key)
    {
      if ($request->hasParameter($key))
      {
        $tag->set($key, $request->getParameter($key));
      }
    }
    
    return $this->renderText($tag->render());
  }

  public function executeSelectCulture(dmWebRequest $request)
  {
    $this->forward404Unless(
      $culture = $request->getParameter('culture'),
      'No culture specified'
    );

    $this->forward404Unless(
      $this->getService('i18n')->cultureExists($culture),
      sprintf('The %s culture does not exist', $culture)
    );

    $this->getUser()->setCulture($culture);

    return $this->redirectBack();
  }

  public function executeRefresh(dmWebRequest $request)
  {
    $this->next = array(
      'type' => 'ajax',
      'url'  => $this->getHelper()->£link('+/dmCore/refreshStep?step=1')->getHref(),
      'msg'  => $this->getService('i18n')->__('Cache clearing')
    );

    $this->setLayout(false);
    
    $this->getUser()->setAttribute('dm_refresh_back_url', $this->getBackUrl());
  }
  
  public function executeRefreshStep(dmWebRequest $request)
  {
    if ($request->hasParameter('dm_use_thread'))
    {
      $this->context->getServiceContainer()
      ->mergeParameter('page_tree_watcher.options', array('use_thread' => $request->getParameter('use_thread')))
      ->reload('page_tree_watcher');
    }
    
    $this->step = $request->getParameter('step');
    
    try
    {
      switch($this->step)
      {
        case 1:
          @$this->context->get('cache_manager')->clearAll();
       
          if ($this->getUser()->can('system'))
          {
            @$this->context->get('filesystem')->sf('dmFront:generate');
      
            @dmFileCache::clearAll();
          }
          
          $data = array(
            'msg'  => $this->getService('i18n')->__('Page synchronization'),
            'type' => 'ajax',
            'url'  => $this->getHelper()->£link('+/dmCore/refreshStep')->param('step', 2)->getHref()
          );
          break;
          
        case 2:
          $this->context->get('page_tree_watcher')->synchronizePages();
          
          $data = array(
            'msg'  => $this->getService('i18n')->__('SEO synchronization'),
            'type' => 'ajax',
            'url'  => $this->getHelper()->£link('+/dmCore/refreshStep')->param('step', 3)->getHref()
          );
          break;
          
        case 3:
          $this->context->get('page_tree_watcher')->synchronizeSeo();
          
          if (count($this->getService('i18n')->getCultures()) > 1)
          {
            $this->context->get('page_i18n_builder')->createAllPagesTranslations();
          }
          
          $data = array(
            'msg'  => $this->getService('i18n')->__('Interface regeneration'),
            'type' => 'redirect',
            'url'  => $this->getUser()->getAttribute('dm_refresh_back_url')
          );
          
          $this->context->getEventDispatcher()->notify(new sfEvent($this, 'dm.refresh', array()));
          $this->getUser()->getAttributeHolder()->remove('dm_refresh_back_url');
          $this->getUser()->logInfo('Project successfully updated');
          break;
      }
    }
    catch(Exception $e)
    {
      $this->getUser()->logError($this->getService('i18n')->__('Something went wrong when updating project'));
      
      $data = array(
        'msg'  => $this->getService('i18n')->__('Something went wrong when updating project'),
        'type' => 'redirect',
        'url'  => $this->getUser()->getAttribute('dm_refresh_back_url')
      );
    
      if (sfConfig::get('sf_debug'))
      {
        if ($request->isXmlHttpRequest())
        {
          $data['url'] = str_replace('dm_xhr=1', 'dm_xhr=0', $request->getUri().'&dm_use_thread=0');
        }
        else
        {
          throw $e;
        }
      }
    }
    
    return $this->renderJson($data);
  }
  
  public function executeMarkdown(dmWebRequest $request)
  {
    return $this->renderText($this->context->get('markdown')->toHtml($request->getParameter('text')));
  }
  
}