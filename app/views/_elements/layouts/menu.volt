<div class="ui fixed inverted main menu">
  <div class="ui container">
    <a class="launch icon item">
      <i class="content icon"></i>
    </a>

    <div class="right menu">
      <div class="ui category search item">
        <div class="ui icon input">
          <input class="prompt" type="text" placeholder="Search accounts...">
          <i class="search icon"></i>
        </div>
        <div class="results"></div>
      </div>
    </div>
  </div>
</div>
<!-- Following Menu -->
<div class="ui inverted top fixed mobile hidden menu">
  <div class="ui container">
    <div class="item" style="background: white">
      <img class="ui avatar image" style="border-radius: 0; width: 24px; height: 24px" src="https://db.morphene.io/explorers/morphene.png"/>
    </div>
    <a href="/" class="header {{ (router.getControllerName() == 'index') ? 'active' : '' }} item">MorpheneDB</span>
    <a href="/accounts" class="{{ (router.getControllerName() == 'account' or router.getControllerName() == 'accounts') ? 'active' : '' }} item">accounts</a>
    <a href="/witnesses" class="{{ (router.getControllerName() == 'witness') ? 'active' : '' }} item">witnesses</a>
    <div class="right menu">
      <div class="item">
        <a href="https://morphene.io/auctions">
          <small>Create Account</small>
        </a>
      </div>
      <div class="ui category search item">
        <div class="ui icon input">
          <input class="prompt" type="text" placeholder="Search accounts...">
          <i class="search icon"></i>
        </div>
        <div class="results"></div>
      </div>
    </div>
  </div>
</div>

<!-- Sidebar Menu -->
<div class="ui vertical inverted sidebar menu">
  <a href="/accounts" class="{{ (router.getControllerName() == 'account' or router.getControllerName() == 'accounts') ? 'active' : '' }} item">accounts</a>
  <a href="/witnesses" class="{{ (router.getControllerName() == 'witness') ? 'active' : '' }} item">witnesses</a>
  <a href="/labs" class="{{ (router.getControllerName() == 'labs') ? 'active' : '' }} item">labs</a>
</div>
