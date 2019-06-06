{% extends 'layouts/homepage.volt' %}

{% block header %}

{% endblock %}

{% block content %}
<style>
.block-animation {
  background-color:red;
  animation: loadin 1s forwards;
  background-color:rgba(105, 205, 100, 1);
}
@keyframes loadin {
    from {background-color:rgba(105, 205, 100, 1);}
    to {background-color:rgba(105, 205, 100, 0);}
}
</style>

<div class="ui body container">
  <h1 class="ui header">
    MorpheneDB
    <div class="sub header">
      Block explorer and database for the MORPH blockchain.
    </div>
  </h1>
  <div class="ui stackable grid">
    <div class="row">
      <div class="sixteen wide column">
      </div>
    </div>
    <div class="row">
      <div class="ten wide column">
        <div class="ui small dividing header">
          <a class="ui tiny blue basic button" href="/blocks" style="float:right">
            View more blocks
          </a>
          Recent Blockchain Activity
          <div class="sub header">
            Displaying most recent irreversible blocks.
          </div>
        </div>
        <div class="ui grid">
          <div class="two column row">
            <div class="column">
              <span class="ui horizontal blue basic label" data-props="head_block_number">
                {{  props['head_block_number'] }}
              </span>
              Current Height
            </div>
            <div class="column">
              <span class="ui horizontal orange basic label" data-props="reversible_blocks">
                {{ props['head_block_number'] - props['last_irreversible_block_num'] }}
              </span>
              Reversable blocks awaiting consensus
            </div>
          </div>
        </div>
        <table class="ui small table" id="blockchain-activity">
          <thead>
            <tr>
              <th>Height</th>
              <th>Transactions</th>
              <th>Operations</th>
            </tr>
          </thead>
          <tbody>
            <tr class="loading center aligned">
              <td colspan="10">
                <div class="ui very padded basic segment">
                  <div class="ui active centered inline loader"></div>
                  <div class="ui header">
                    Waiting for new irreversible blocks
                  </div>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="six wide centered column">
        <div class="ui small header">
          Consensus State
        </div>
        <table class="ui small definition table" id="state">
          <tbody>
            <tr>
              <td class="eight wide">MORPH Inflation Rate</td>
              <td>
                {{ inflation }}
              </td>
            </tr>
            <tr>
              <td class="eight wide">Account Creation Fee</td>
              <td>
                <span data-state-witness-median="account_creation_fee">
                  <i class="notched circle loading icon"></i>
                </span>
              </td>
            </tr>
            <tr>
              <td>Maximum Block Size</td>
              <td>
                <span data-state-witness-median="maximum_block_size">
                  <i class="notched circle loading icon"></i>
                </span>
              </td>
            </tr>
          </tbody>
        </table>
        <div class="ui small header">
          Network Performance
        </div>
        <table class="ui small definition table" id="state">
          <tbody>
            <tr>
              <td class="eight wide">Transactions per second (24h)</td>
              <td>
                {{ tx_per_sec }} tx/sec
              </td>
            </tr>
            <tr>
              <td class="eight wide">Transactions per second (1h)</td>
              <td>
                {{ tx1h_per_sec }} tx/sec
              </td>
            </tr>
            <tr>
              <td>Transactions over 24h</td>
              <td>
                {{ tx }} txs
              </td>
            </tr>
            <tr>
              <td>Transactions over 1h</td>
              <td>
                {{ tx1h }} txs
              </td>
            </tr>
            <tr>
              <td>Operations over 24h</td>
              <td>
                {{ op }} ops
              </td>
            </tr>
            <tr>
              <td>Operations over 1h</td>
              <td>
                {{ op1h }} ops
              </td>
            </tr>
          </tbody>
        </table>

        <div class="ui small header">
          Global Properties
        </div>
        <table class="ui small definition table" id="global_props">
          <tbody>
            {% for key, value in props %}
              {% if key not in ['id', 'morph_per_mvests', 'head_block_id', 'recent_slots_filled', 'head_block_number'] %}
                <tr>
                  <td class="eight wide">{{ key }}</td>
                  <td data-props="{{ key }}">{{ value }}</td>
                </tr>
              {% endif %}
            {% endfor %}
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>


{% endblock %}

{% block scripts %}
<script type="text/javascript">
   var sock = null;
   var ellog = null;

   window.onload = function() {

      var wsuri;
      ellog = document.getElementById('log');

      if (window.location.hostname === "localhost") {
         wsuri = "ws://localhost:8888";
      } else {
         wsuri = "wss://" + window.location.hostname + ":443/ws";
      }

      if ("WebSocket" in window) {
         sock = new WebSocket(wsuri);
      } else if ("MozWebSocket" in window) {
         sock = new MozWebSocket(wsuri);
      } else {
        //  log("Browser does not support WebSocket!");
      }

      if (sock) {
         sock.onopen = function() {
            // log("Connected to " + wsuri);
         }

         sock.onclose = function(e) {
            // log("Connection closed (wasClean = " + e.wasClean + ", code = " + e.code + ", reason = '" + e.reason + "')");
            sock = null;
         }

         sock.onmessage = function(e) {
            var data = JSON.parse(e.data);
            if(data.props) {
              $.each(data.props, function(key, value) {
                $("[data-props="+key+"]").html(value);
              });
            }
            if(data.state) {
              $.each(data.state.witness_schedule, function(key, value) {
                $("[data-state-witness="+key+"]").html(value);
              });
              $.each(data.state.witness_schedule.median_props, function(key, value) {
                $("[data-state-witness-median="+key+"]").html(value);
              });
            }
            if(data.block) {
              var tbody = $("#blockchain-activity tbody"),
                  row = $("<tr class='block-animation'>"),
                  rows = tbody.find("tr"),
                  rowLimit = 19,
                  count = rows.length,
                  // Block Height
                  height_header = $("<div class='ui small header'>"),
                  height_header_link = $("<a>").attr("href", "/block/" + data.block.height).attr("target", "_blank").html("#"+data.block.height),
                  height_header_time = $("<div class='sub header'>").html(data.block.ts),
                  height = $("<td>").append(height_header.append(height_header_link, height_header_time)),
                  // Transactions
                  tx = $("<td>").append(data.block.opCount),
                  ops = $("<td>");
              $.each(data.block.opCounts, function(key, value) {
                var label = $("<span class='ui tiny basic label'>").append(key + " (" + value + ")");
                ops.append(label);
              });
              tbody.find("tr.loading").remove();
              row.append(height, tx, ops);
              tbody.prepend(row);
              if(count > rowLimit) {
                rows.slice(rowLimit-count).remove();
              }
            }
            // log(JSON.stringify(data));
         }
      }
   };

  //  function broadcast() {
  //     var account = document.getElementById('account').value;
  //     if (sock) {
  //        sock.send(account);
  //        log("Subscribed account: " + account);
  //     } else {
  //        log("Not connected.");
  //     }
  //  };

   function log(m) {
      ellog.innerHTML += m + '\n';
      ellog.scrollTop = ellog.scrollHeight;
   };
</script>
{% endblock %}
