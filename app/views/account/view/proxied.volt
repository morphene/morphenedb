<h3 class="ui header">
  Witness Voting Proxies
  <div class="sub header">
    The following accounts have their weight proxied to @{{ account.name }} for witness voting.
  </div>
</h3>
<table class="ui attached table">
  <thead>
    <tr>
      <th>Account</th>
      <th class="right aligned">Vests</th>
      <th class="right aligned">Balance</th>
    </tr>
  </thead>
  <tbody>
    {% for proxy in proxied %}
    <tr>
      <td>
        <div class="ui header">
          <div class="ui circular blue label">
            420
          </div>
          {{ link_to("/@" ~ proxy.name, proxy.name) }}
        </div>
      </td>
      <td class="collapsing right aligned">
        {{ partial("_elements/vesting_shares", ['current': proxy]) }}
      </td>
      <td class="collapsing right aligned">
        <div class="ui small header">
          <?php echo number_format($proxy->total_balance, 3, ".", ",") ?> MORPH
        </div>
      </td>
    </tr>
    {% endfor %}
  </tbody>
</table>
