<h3 class="ui header">
  Recent History
  <div class="sub header">
    All recent activity involving @{{ account.name }}.
  </div>
</h3>

<table class="ui stackable definition table">
  <thead></thead>
  <tbody>
  {% for item in activity %}
  <tr>
    <td class="three wide">
      <div class="ui small header">
        <?php echo $this->opName::string($item['op'], $account) ?>
        <div class="sub header">
          <?php echo $this->timeAgo::string($item['timestamp']); ?>
          <br><a href="/block/{{ item['block' ]}}"><small style="color: #bbb">Block #{{ item['block' ]}}</small></a>
        </div>
      </div>
    </td>
    <td>
      {% include "_elements/definition_table" with ['data': item['op']] %}
    </td>
  </tr>
  {% else %}
  <tr>
    <td>
      Unable to connect to morphened for to load recent history.
    </td>
  </tr>
  {% endfor %}
  </tbody>
</table>
