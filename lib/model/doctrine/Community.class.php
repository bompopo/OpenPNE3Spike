<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

class Community extends BaseCommunity implements opAccessControlRecordInterface
{
  public function getImageFileName()
  {
    if ($this->File)
    {
      return $this->File->name;
    }

    return '';
  }

  public function getConfigs()
  {
    $configs = sfConfig::get('openpne_community_config');

    $myConfigs = Doctrine::getTable('CommunityConfig')->retrievesByCommunityId($this->id);

    $result = array();

    // initialize
    foreach ($configs as $key => $config)
    {
      $result[$config['Caption']] = '';
      if (isset($config[$key]['Default']))
      {
        $result[$config['Caption']] = $config[$key]['Default'];
      }
    }
    
    // set my configure
    foreach ($myConfigs as $myConfig)
    {
      $name = $myConfig->getName();
      if (isset($configs[$name]))
      {
        switch ($configs[$name]['FormType'])
        {
          case 'checkbox' :
          // FIXME
          case 'radio' :
          case 'select' :
            $value = $myConfig->getValue();
            if (isset($configs[$name]['Choices'][$value]))
            {
              $i18n = sfContext::getInstance()->getI18N();
              $result[$configs[$name]['Caption']] = $i18n->__($configs[$name]['Choices'][$value]);
            }
            break;
          default :
            $result[$configs[$name]['Caption']] = $myConfig->getValue();
        }
        $configs[$myConfig->getName()] = $myConfig->getValue();
      }
    }

    return $result;
  }

  public function getConfig($configName)
  {
    $config = Doctrine::getTable('CommunityConfig')->retrieveByNameAndCommunityId($configName, $this->getId());

    if (!$config)
    {
      return null;
    }

    return $config->value;
  }

  public function getMembers($limit = null, $isRandom = false)
  {
    $communityMembers = Doctrine::getTable('CommunityMember')->createQuery()
      ->where('community_id = ?', $this->id)
      ->andWhere('position <> ?', 'pre')
      ->execute();

    $q = Doctrine::getTable('Member')->createQuery()
      ->whereIn('id', array_values($communityMembers->toKeyValueArray('id', 'member_id')));

    if (!is_null($limit))
    {
      $q->limit($limit);
    }

    if ($isRandom)
    {
      $expr = new Doctrine_Expression('RANDOM()');
      $q->orderBy($expr);
    }

    return $q->execute();
  }

  public function getAdminMember()
  {
    return Doctrine::getTable('CommunityMember')->getCommunityAdmin($this->getId())->getMember();
  }

  public function checkPrivilegeBelong($memberId)
  {
    if (!$this->isPrivilegeBelong($memberId))
    {
      throw new opPrivilegeException('fail');
    }
  }

  public function isPrivilegeBelong($memberId)
  {
    return Doctrine::getTable('CommunityMember')->isMember($memberId, $this->id);
  }

  public function isAdmin($memberId)
  {
    return Doctrine::getTable('CommunityMember')->isAdmin($memberId, $this->id);
  }

  public function countCommunityMembers()
  {
    $inactiveMemberIds = Doctrine::getTable('Member')->getInactiveMemberIds();

    return Doctrine::getTable('CommunityMember')->createQuery()
      ->whereNotIn('member_id', $inactiveMemberIds)
      ->andWhere('community_id = ?', $this->id)
      ->andWhere('position <> ?', 'pre')
      ->count();
  }

  public function getNameAndCount($format = '%s (%d)')
  {
    return sprintf($format, $this->getName(), $this->countCommunityMembers());
  }

  public function getRegisterPolicy()
  {
    $register_policy = $this->getConfig('register_poricy');
    if ('open' === $register_policy)
    {
      return 'Everyone can join';
    }
    else if ('close' === $register_policy)
    {
      return '%Community%\'s admin authorization needed';
    }
  }

  public function getChangeAdminRequestMember()
  {
    $memberId = Doctrine::getTable('CommunityMember')->createQuery()
      ->select('member_id')
      ->where('community_id = ?', $this->getId())
      ->andWhere('position = ?', 'admin_confirm')
      ->execute(array(), Doctrine::HYDRATE_SINGLE_SCALAR);

    if ($memberId)
    {
      return Doctrine::getTable('Member')->find($memberId);
    }
    return null;
  }

  public function generateRoleId(Member $member)
  {
    if (Doctrine::getTable('CommunityMember')->isAdmin($member->id, $this->id))
    {
      return 'admin';
    }
    elseif (Doctrine::getTable('CommunityMember')->isMember($member->id, $this->id))
    {
      return 'member';
    }

    return 'everyone';
  }
}