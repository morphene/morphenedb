{% extends 'layouts/default.volt' %}

{% block header %}

{% endblock %}

{% block content %}
<div class="ui vertical stripe segment" style="background-color: #e0e0e0 !important">
  <div class="ui middle aligned stackable grid container">
    <div class="row">
      <div class="column">
        <div class="ui huge header">
          Accounts
        </div>
        <div class="ui top attached menu">
          <div class="ui dropdown item">
            Richlist
            <i class="dropdown icon"></i>
            <div class="menu">
              <a class="{{ filter == 'vest' ? 'active' : '' }} item" href="/accounts/vest">
                VESTS
              </a>
              <a class="{{ filter == 'morph' ? 'active' : '' }} item" href="/accounts/morph">
                MORPH
              </a>
              <a class="{{ filter == 'powerdown' ? 'active' : '' }} item" href="/accounts/powerdown">
                Power Down
              </a>
            </div>
          </div>
          <div class="right menu">
            <div class="item">
              Data updated <?php echo $this->timeAgo::mongo($accounts[0]->scanned); ?>
            </div>
          </div>
        </div>
        <table class="ui attached table">
          <thead>
            <tr>
              <th>Account</th>
              <th class="right aligned">Vests</th>
              <th class="right aligned">Powerdown</th>
              <th class="right aligned">Balance</th>
            </tr>
          </thead>
          <tbody>
            {% for account in accounts %}
            <tr>
              <td>
                <div class="ui header">
                  <a href="/@{{account.name}}">{{ account.name }}</a>
                </div>
              </td>
              <td class="collapsing right aligned">
                {{ partial("_elements/vesting_shares", ['current': account]) }}
              </td>
              <td class="collapsing right aligned">
                <?php if(is_numeric($account->vesting_withdraw_rate) && $account->vesting_withdraw_rate > 0.01): ?>
                  <div data-popup data-content="<?php echo number_format($current->vesting_withdraw_rate, 3, ".", ",") ?> VESTS" data-variation="inverted" data-position="left center">
                    <?php echo $this->largeNumber::format($current->vesting_withdraw_rate); ?> (<?php echo round($current->vesting_withdraw_rate / $current->vesting_shares * 100, 2) ?>%)
                  </div>
                  +<?php echo $this->convert::vest2sp($current->vesting_withdraw_rate); ?>/Week
                <?php endif; ?>
              </td>
              <td class="collapsing right aligned">
                <div class="ui small header">
                  <?php echo number_format($account->total_balance, 3, ".", ",") ?> MORPH
                </div>
              </td>
            </tr>
            {% endfor %}
          </tbody>
        </table>
      </div>
    </div>
    <div class="row">
      <div class="column">
        {% include "_elements/paginator.volt" %}
      </div>
    </div>
  </div>
</div>
{% endblock %}
