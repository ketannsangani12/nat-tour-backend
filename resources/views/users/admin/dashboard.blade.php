@extends('layouts.default-2')
@php
$uTypeAdmin = session('userType') == 'Admin';
@endphp

@section('main-content')
<section class="py-5 bg-[#F9FAFB]">
  <div class="w-full container">
    <div class="flex flex-wrap -mx-3">
      @if (isPermissionGranted() == false)
        <div class="w-1/3 max-w-full px-3">
          <h2 class="font-semibold text-2xl max-[1350px]:text-lg text-[#111827]">Dashboard</h2>
        </div>
        @if($uTypeAdmin)
          <div class="w-1/3 max-w-full px-3" style="float:right; display: none;">
            <div class="card-tools d-flex">
              <select class="form-select margin-t-2px" id="tour_operator">
                @foreach($tourOperators as $tourOp)
                  <option value="{{$tourOp->id}}" selected="selected">{{$tourOp->name}}</option>
                @endforeach
              </select>
            </div>
          </div>
        @endif
        <div class="w-2/3 max-w-full px-3">
          <div class="card-tools flex items-center justify-end">
            <label class="text-lg font-medium text-[#2C2421] mr-2" id="selectedChartOption">Today</label>
            <button type="button" class="btn btn-primary btn-sm daterange bg-white border border-[#E6E6E6] p-2 rounded-md" title="Date range" id="daterange">
              <svg width="16" height="17" viewBox="0 0 16 17" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5.33594 1.83594V4.5026" stroke="#4B5563" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.6641 1.83594V4.5026" stroke="#4B5563" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"/><path d="M12.6667 3.16406H3.33333C2.59695 3.16406 2 3.76102 2 4.4974V13.8307C2 14.5671 2.59695 15.1641 3.33333 15.1641H12.6667C13.403 15.1641 14 14.5671 14 13.8307V4.4974C14 3.76102 13.403 3.16406 12.6667 3.16406Z" stroke="#4B5563" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 7.16406H14" stroke="#4B5563" stroke-width="1.33" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
          </div>
        </div>
      @else
        <span style="font-semibold text-2xl max-[1350px]:text-lg text-[#111827] mb-4 max-[1350px]:mb-3">
          Dashboard
        </span>
      @endif
    </div>
  </div>
</section>

@if (isPermissionGranted() == false)
<!-- Main content -->
<section class="bg-[#F9FAFB]">
  <div class="w-full container">
    <div class="flex flex-wrap -mx-3">
      <div class="w-full sm:w-1/2 md:w-1/3 max-w-full px-3 mb-6">
        <div class="bg-[#F2FDFB] border border-[#E6E6E6] rounded-xl py-5 px-6 max-[1350px]:p-4 shadow-sm flex items-center justify-between">
          <div class="inner">
            <p class="text-xs font-medium text-[#737373] mb-1 uppercase">Total Amount</p>
            <h3 class="text-xl font-semibold text-[#171717]">{{ currencyFormation(false, true) }}<span id="totalAmount">{{$Total_Amount}}</span></h3>
          </div>
          <div class="bg-white border border-[#E6E6E6] p-2 rounded-md">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M8 1.33594V14.6693" stroke="#60BEBD" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M11.3333 3.33594H6.33333C5.71449 3.33594 5.121 3.58177 4.68342 4.01936C4.24583 4.45694 4 5.05043 4 5.66927C4 6.28811 4.24583 6.8816 4.68342 7.31919C5.121 7.75677 5.71449 8.0026 6.33333 8.0026H9.66667C10.2855 8.0026 10.879 8.24844 11.3166 8.68602C11.7542 9.12361 12 9.7171 12 10.3359C12 10.9548 11.7542 11.5483 11.3166 11.9859C10.879 12.4234 10.2855 12.6693 9.66667 12.6693H4" stroke="#8CC3A7" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
        </div>
      </div>
      <div class="w-full sm:w-1/2 md:w-1/3 max-w-full px-3 mb-6">
        <div class="bg-[#EEF7FF] border border-[#E6E6E6] rounded-xl py-5 px-6 max-[1350px]:p-4 shadow-sm flex items-center justify-between">
          <div class="inner">
            <p class="text-xs font-medium text-[#737373] mb-1 uppercase">Total Orders</p>
            <h3 class="text-xl font-semibold text-[#171717]" id="totalOrder">{{$Total_Order}}</h3>
          </div>
          <div class="bg-white border border-[#E6E6E6] p-2 rounded-md">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5.33594 1.33594V4.0026" stroke="#264F73" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.6641 1.33594V4.0026" stroke="#264F73" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M12.6667 2.66406H3.33333C2.59695 2.66406 2 3.26102 2 3.9974V13.3307C2 14.0671 2.59695 14.6641 3.33333 14.6641H12.6667C13.403 14.6641 14 14.0671 14 13.3307V3.9974C14 3.26102 13.403 2.66406 12.6667 2.66406Z" stroke="#264F73" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 6.66406H14" stroke="#264F73" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
        </div>
      </div>
      <div class="w-full sm:w-1/2 md:w-1/3 max-w-full px-3 mb-6">
        <div class="bg-[#FDF8FF] border border-[#E6E6E6] rounded-xl py-5 px-6 max-[1350px]:p-4 shadow-sm flex items-center justify-between">
          <div class="inner">
            <p class="text-xs font-medium text-[#737373] mb-1 uppercase">Total Customers</p>
            <h3 class="text-xl font-semibold text-[#171717]" id="totalCustomer">{{ $Total_Customer }}</h3>
          </div>
          <div class="bg-white border border-[#E6E6E6] p-2 rounded-md">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M10.6693 14V12.6667C10.6693 11.9594 10.3883 11.2811 9.88822 10.781C9.38813 10.281 8.70985 10 8.0026 10H4.0026C3.29536 10 2.61708 10.281 2.11699 10.781C1.61689 11.2811 1.33594 11.9594 1.33594 12.6667V14" stroke="#D63A1F" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M6.0026 7.33333C7.47536 7.33333 8.66927 6.13943 8.66927 4.66667C8.66927 3.19391 7.47536 2 6.0026 2C4.52984 2 3.33594 3.19391 3.33594 4.66667C3.33594 6.13943 4.52984 7.33333 6.0026 7.33333Z" stroke="#D63A1F" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M14.6641 13.9993V12.6659C14.6636 12.0751 14.467 11.5011 14.105 11.0341C13.743 10.5672 13.2361 10.2336 12.6641 10.0859" stroke="#D63A1F" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/><path d="M10.6641 2.08594C11.2377 2.2328 11.7461 2.5664 12.1092 3.03414C12.4722 3.50188 12.6693 4.07716 12.6693 4.66927C12.6693 5.26138 12.4722 5.83666 12.1092 6.3044C11.7461 6.77214 11.2377 7.10574 10.6641 7.2526" stroke="#D63A1F" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<!-- Chart Report Section -->

<!-- Main content -->
<section class="wrapper bg-[#F9FAFB]">
  <div class="w-full container">
    <div class="flex flex-wrap -mx-3">
      <div class="w-full xl:w-1/2 max-w-full px-3 mb-6">
        <div class="card card-info border border-[#E6E6E6] bg-white shadow-sm rounded-xl px-7 py-6 max-[1350px]:p-4">
          <div class="card-header">
            <h3 class="card-title text-sm font-medium text-[#737373] mb-5 uppercase">Booking by Date</h3>
            <div class="card-tools">

            </div>
          </div>
          <div class="card-body">
            <canvas class="chart" id="line-chart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
          </div>
        </div>
      </div>

      <div class="w-full xl:w-1/2 max-w-full px-3 mb-6">
        <div class="card card-danger border border-[#E6E6E6] bg-white shadow-sm rounded-xl px-7 py-6 max-[1350px]:p-4">
          <div class="card-header">
            <h3 class="card-title text-sm font-medium text-[#737373] mb-5 uppercase">Booking by Packages</h3>
            <div class="card-tools">

            </div>
          </div>
          <div class="card-body">
            <canvas id="donutChart" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>
<!-- /.content -->

<!-- Booking Details Report -->
<section class="bg-[#F9FAFB]">
  <div class="w-full container">
    <div class="flex flex-wrap -mx-3">
      <div class="w-full xl:w-1/2 max-w-full px-3 mb-6">
        <div class="card border border-[#E6E6E6] bg-white shadow-sm rounded-xl p-5 max-[1350px]:p-4">
          <div class="card-header">
            <h3 class="card-title text-sm font-medium text-[#737373] mb-4 uppercase">Customer Details</h3>
            <div class="card-tools">

            </div>
          </div>
          <div class="card-body table-responsive max-h-80 overflow-y-auto border border-[#E2E8F0] rounded-lg">
            <table class="table table-striped table-valign-middle w-full">
              <thead>
                <tr class="bg-[#F9FAFB] sticky -top-[1px] z-10">
                  <th class="text-xs font-medium text-[#374151] p-4 text-left">Name</th>
                  <th class="text-xs font-medium text-[#374151] p-4 text-left">Phone</th>
                  <th class="text-xs font-medium text-[#374151] p-4 text-left">Email</th>
                </tr>
              </thead>
              <tbody id="customerDetailsBody">

            </table>
          </div>
        </div>
        <!-- /.card -->
      </div>

      <div class="w-full xl:w-1/2 max-w-full px-3 mb-6">
        <div class="card border border-[#E6E6E6] bg-white shadow-sm rounded-xl p-5 max-[1350px]:p-4">
          <div class="card-header border-0">
            <h3 class="card-title text-sm font-medium text-[#737373] mb-4 uppercase">Booking Details</h3>
            <div class="card-tools">

            </div>
          </div>
          <div class="card-body table-responsive max-h-80 overflow-y-auto border border-[#E2E8F0] rounded-lg">
            <table class="table table-striped table-valign-middle w-full">
              <thead>
                <tr class="bg-[#F9FAFB] sticky -top-[1px] z-10">
                  <th class="text-xs font-medium text-[#374151] p-4 text-left">ID</th>
                  <th class="text-xs font-medium text-[#374151] p-4 text-left">Date</th>
                  <th class="text-xs font-medium text-[#374151] p-4 text-left">Seat</th>
                  <th class="text-xs font-medium text-[#374151] p-4 text-left">Amount</th>
                  <th class="text-xs font-medium text-[#374151] p-4 text-left">Status</th>
                  <th class="text-xs font-medium text-[#374151] p-4 text-left">Package</th>
                </tr>
              </thead>
              <tbody id="bookingDetailsBody">

              </tbody>
            </table>
          </div>
        </div>
        <!-- /.card -->
      </div>

    </div>
    <!-- /.row -->
  </div>
  <!-- /.content -->
</section>
@endif
@endsection

@if (isPermissionGranted() == false)
@section('css-libraries')
<link href="{{ asset('plugins/daterangepicker-3.1/daterangepicker.css') }}" rel="stylesheet">
@endsection

@section('css-custom')
<link href="{{ asset('css/user-common/dashboard.css') }}" rel="stylesheet">
@endsection

@section('js-libraries')
<script src="{{ asset('plugins/chart.js-2.9.4/Chart.min.js') }}"></script>
<script src="{{ asset('plugins/moment-2.29.1.min.js')}}"></script>
<script src="{{ asset('plugins/daterangepicker-3.1/daterangepicker.js')}}"></script>
@endsection

@section('js-custom')
<script src="{{ asset('js/user-common/dashboard.js') }}"></script>

<script>
  function f2() {}

  var dashboardChartGetterURL = "{{ route('admin.dashboardChartGetter', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_', 'tourOperatorId' => 0 ]) }}";
  var dashboardLineChartGetterURL = "{{ route('admin.dashboardLineChartGetter', [ 'fromDate' => '_FROM_DATE_', 'toDate' => '_TO_DATE_', 'tourOperatorId' => 0 ]) }}";
  var dashboardDetailsGetterURL = "{{ route('admin.dashboarddetails', [ 'tourOperatorId' => 0 ]) }}";

  $(function() {
    //-------------
    //- DONUT CHART -
    //-------------
    // Get context with jQuery - using jQuery's .get() method.

    var labels = [];
    var countData = [];

    var today = new Date();
    var dd = String(today.getDate()).padStart(2, '0');
    var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
    var yyyy = today.getFullYear();

    var selectedTourOperator = $('#tour_operator').val();

    today = yyyy + '-' + mm + '-' + dd;

    $.getJSON(dashboardDetailsGetterURL + selectedTourOperator)
      .done(function(data) {
        if (data.totalAmount[0].totalAmount !== "" && data.totalAmount[0].totalAmount !== null) {
          $('#totalAmount').text((parseFloat(data.totalAmount[0].totalAmount)));
        } else {
          $('#totalAmount').text(0);
        }

        if (data.totalOrder[0].totalOrder !== "" && data.totalOrder[0].totalOrder !== null) {
          $('#totalOrder').text(data.totalOrder[0].totalOrder);
        } else {
          $('#totalOrder').text(0);
        }

        if (data.totalCustomer[0].totalCustomers !== "" && data.totalCustomer[0].totalCustomers !== null) {
          $('#totalCustomer').text(data.totalCustomer[0].totalCustomers);
        } else {
          $('#totalCustomer').text(0);
        }

        // customer Details and Booking Details
        var html = "";
        if (data.length != 0) {
          data.customerDetails.map((item) => {
            html += "<tr class='border-t border-[#E2E8F0] hover:bg-[#F0FDF4]'><td class='text-sm font-medium text-[#171717] p-4'>" + item.name + "</td><td class='text-sm text-[#171717] p-4'>" + item.phone_number + "</td><td class='text-sm text-[#171717] p-4'>" + item.email + "</td></tr>";
          });
          $('#customerDetailsBody').append(html);
        } else {
          $('#customerDetailsBody').append("<h5 class='text-base font-medium text-[#171717]'>No Data Available</h5>");
        }
        var bookingHtml = "";
        if (data.length != 0) {
          data.bookingDetails.map((item) => {
            if (item.is_affiliate == 'Yes') {
              bookingHtml += "<tr class='af_warning border-t border-[#E2E8F0] bg-[#FFEDD5] hover:bg-[#F0FDF4]'><td class='text-sm font-medium text-[#171717] p-4'>" + item.id + "</td><td class='text-sm text-[#171717] p-4'>" + item.created_at + "</td><td class='text-sm text-[#171717] p-4'>" + item.tourists + "</td><td class='text-sm font-semibold text-[#171717] p-4'>" + currencyFormation.symbol + item.total + "</td><td class='text-sm text-[#171717] p-4'><span class='text-xs font-semibold text-[#047857] px-2 py-1 bg-[#ECFDF5] border border-[#A7F3D0] rounded-full'>" + item.status + "</span></td><td class='text-sm text-[#171717] p-4'>" + item.name + "</td></tr>";
            } else {
              bookingHtml += "<tr class='border-t border-[#E2E8F0] hover:bg-[#F0FDF4]'><td class='text-sm font-medium text-[#171717] p-4'>" + item.id + "</td><td class='text-sm text-[#171717] p-4'>" + item.created_at + "</td><td class='text-sm text-[#171717] p-4'>" + item.tourists + "</td><td class='text-sm font-semibold text-[#171717] p-4'>" + currencyFormation.symbol + item.total + "</td><td class='text-sm text-[#171717] p-4'><span class='text-xs font-semibold text-[#047857] px-2 py-1 bg-[#ECFDF5] border border-[#A7F3D0] rounded-full'>" + item.status + "</span></td><td class='text-sm text-[#171717] p-4'>" + item.name + "</td></tr>";
            }
          });
          $('#bookingDetailsBody').append(bookingHtml);
        } else {
          $('#bookingDetailsBody').append("<h5 class='text-base font-medium text-[#171717]'>No Data Available</h5>");
        }
      });

    // Start of Sales chart

    var lineLabels = [];
    var chartData = [];
    var packageName = "";
    var linesOfChart = [];
    var newData;
    let week = [];
    let currentArrayWeek = [];
    let check = [];

    $.getJSON(dashboardLineChartGetterURL.replace('_FROM_DATE_', today).replace('_TO_DATE_', today) + selectedTourOperator)
      .done(function(data) {
        if (data) {
          for (var main in data) {
            let colorGetter = (Math.random() * 0xfffff * 1000000).toString(16);
            let colorFormatter = '#' + colorGetter.slice(0, 6);
            var startDateValue = moment(today);
            var endDateValue = moment(today);
            var diffDays = endDateValue.diff(startDateValue, 'days');

            var test = 0;
            for (var children in data[main]) {
              packageName = data[main][children].name;
              if (diffDays == 30 || diffDays == 31 || diffDays == 28 || diffDays == 29) {
                let weekNumber = Math.floor((data[main][children].date - 1) / 7) + 1; // To give week number
                week.push("Week " + weekNumber);
              } else if (diffDays == 365 || diffDays == 366 || diffDays == 364) {
                // let weekNumber = Math.floor((data[main][children].date - 1) / 7) + 1; // To give week number
                week.push(data[main][children].date);
              } else {
                week.push(data[main][children].date);
              }
              currentArrayWeek.push(data[main][children].date);

              week.map((item) => {
                if (!currentArrayWeek.includes(item)) {
                  if (!check.includes(item)) {
                    chartData.push(0);
                  }
                  check.push(item);
                } else {
                  if (diffDays == 30 || diffDays == 31 || diffDays == 28 || diffDays == 29) {
                    let weekNumber = Math.floor((data[main][children].date - 1) / 7) + 1; // To give week number
                    if (item == "Week " + weekNumber) {
                      chartData.push(parseInt(data[main][children].totalTourists));
                    }
                  } else if (diffDays == 365 || diffDays == 366 || diffDays == 364) {
                    if (item == data[main][children].date) {
                      chartData.push(parseInt(data[main][children].totalTourists));
                    }
                  } else {
                    if (item == data[main][children].date) {
                      chartData.push(parseInt(data[main][children].totalTourists));
                    }
                  }
                }
              })
              test++;
            }


            // Lines of line chart
            newData = {
              label: packageName,
              fill: false,
              borderWidth: 2,
              lineTension: 0,
              spanGaps: true,
              borderColor: colorFormatter,
              pointRadius: 3,
              pointHoverRadius: 7,
              pointColor: colorFormatter,
              pointBackgroundColor: colorFormatter,
              data: chartData.filter((v, i, a) => a.indexOf(v) === i),
            };

            linesOfChart.push(newData);
            chartData = [];
            currentArrayWeek = [];
          }
          var salesGraphChartCanvas = $('#line-chart').get(0).getContext('2d')
          // $('#revenue-chart').get(0).getContext('2d');

          if (window.salesGraphChart != undefined)
            window.salesGraphChart.destroy();


          var salesGraphChartData = {
            labels: week.filter((v, i, a) => a.indexOf(v) === i),
            datasets: linesOfChart
          }

          var salesGraphChartOptions = {
            maintainAspectRatio: false,
            responsive: true,
            legend: {
              display: true
            },
            scales: {
              xAxes: [{
                ticks: {
                  fontColor: '#000'
                },
                gridLines: {
                  display: false,
                  color: '#000',
                  drawBorder: false
                }
              }],
              yAxes: [{
                ticks: {
                  stepSize: 10,
                  fontColor: '#000'
                },
                // gridLines: {
                //   display: true,
                //   color: '#000',
                //   drawBorder: false
                // }
              }]
            }
          }

          window.salesGraphChart = new Chart(salesGraphChartCanvas, { // lgtm[js/unused-local-variable]
            type: 'line',
            data: salesGraphChartData,
            options: salesGraphChartOptions
          })

        }
      });

    // END of sales chart

    $.getJSON(dashboardChartGetterURL.replace('_FROM_DATE_', today).replace('_TO_DATE_', today) + selectedTourOperator)
      .done(function(data) {
        data['bookingCount'].map((item) => {
          labels.push(item.name);
          countData.push(item.tourCount);
        });
        if (window.myChart != undefined)
          window.myChart.destroy();
        var donutChartCanvas = $('#donutChart').get(0).getContext('2d')
        var donutData = {
          labels: labels,
          datasets: [{
            backgroundColor: ['#f56954', '#00a65a', '#f39c12', '#00c0ef', '#3c8dbc', '#d2d6de'],
            data: countData,
          }]
        }
        var donutOptions = {
          // maintainAspectRatio : false,
          // responsive : true,
          scales: {
            yAxes: [{
              display: true,
              ticks: {
                beginAtZero: true
              }
            }]
          },
          legend: {
            display: false
          },
          title: {
            display: false,
            text: ""
          }
        }
        //Create pie or douhnut chart
        // You can switch between pie and douhnut using the method below.
        window.myChart = new Chart(donutChartCanvas, {
          type: 'bar',
          data: donutData,
          options: donutOptions
        });
      });
  });

  $('#tour_operator').change(function() {
    var selectedTourOperator = $(this).val();
    $('#customerDetailsBody').empty();
    $('#bookingDetailsBody').empty();

    // Today Wise Total Count Start
    $.getJSON(dashboardDetailsGetterURL + selectedTourOperator)
      .done(function(data) {

        if (data.totalAmount[0].totalAmount !== "") {
          $('#totalAmount').text(parseFloat(data.totalAmount[0].totalAmount).toFixed(2));
        } else {
          $('#totalAmount').text(0);
        }

        if (data.totalOrder[0].totalOrder !== "") {
          $('#totalOrder').text(data.totalOrder[0].totalOrder);
        } else {
          $('#totalOrder').text(0);
        }

        if (data.totalCustomer[0].totalCustomers !== "") {
          $('#totalCustomer').text(data.totalCustomer[0].totalCustomers);
        } else {
          $('#totalCustomer').text(0);
        }

        var html = "";
        if (data.length != 0) {
          data.customerDetails.map((item) => {
            html += "<tr class='border-t border-[#E2E8F0] hover:bg-[#F0FDF4]'><td class='text-sm font-medium text-[#171717] p-4'>" + item.name + "</td><td class='text-sm text-[#171717] p-4'>" + item.phone_number + "</td><td class='text-sm text-[#171717] p-4'>" + item.email + "</td></tr>";
          });
          $('#customerDetailsBody').append(html);
        } else {
          $('#customerDetailsBody').append("<h5 class='text-base font-medium text-[#171717]'>No Data Available</h5>");
        }
        var bookingHtml = "";
        if (data.length != 0) {

          data.bookingDetails.map((item) => {
            if (item.is_affiliate == 'Yes') {
              bookingHtml += "<tr class='bg-warning border-t border-[#E2E8F0] bg-[#FFEDD5] hover:bg-[#F0FDF4]'><td class='text-sm font-medium text-[#171717] p-4'>" + item.id + "</td><td class='text-sm text-[#171717] p-4'>" + item.created_at + "</td><td class='text-sm text-[#171717] p-4'>" + item.tourists + "</td><td class='text-sm font-semibold text-[#171717] p-4'>" + currency(item.total).format() + "</td><td class='text-sm text-[#171717] p-4'><span class='text-xs font-semibold text-[#047857] px-2 py-1 bg-[#ECFDF5] border border-[#A7F3D0] rounded-full'>" + item.status + "</span></td><td class='text-sm text-[#171717] p-4'>" + item.name + "</td></tr>";
            } else {
              bookingHtml += "<tr class='border-t border-[#E2E8F0] hover:bg-[#F0FDF4]'><td class='text-sm font-medium text-[#171717] p-4'>" + item.id + "</td><td class='text-sm text-[#171717] p-4'>" + item.created_at + "</td><td class='text-sm text-[#171717] p-4'>" + item.tourists + "</td><td class='text-sm font-semibold text-[#171717] p-4'>" + currency(item.total).format() + "</td><td class='text-sm text-[#171717] p-4'><span class='text-xs font-semibold text-[#047857] px-2 py-1 bg-[#ECFDF5] border border-[#A7F3D0] rounded-full'>" + item.status + "</span></td><td class='text-sm text-[#171717] p-4'>" + item.name + "</td></tr>";
            }
          });
          $('#bookingDetailsBody').append(bookingHtml);
        } else {
          $('#bookingDetailsBody').append("<h5 class='text-base font-medium text-[#171717]'>No Data Available</h5>");
        }
      });
    // Today Wise Total Count Start
    // End of filling details

    var labels = [];
    var countData = [];

    var today = new Date();
    var dd = String(today.getDate()).padStart(2, '0');
    var mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
    var yyyy = today.getFullYear();

    today = yyyy + '-' + mm + '-' + dd;

    $('#selectedChartOption').text("Today");

    $.getJSON(dashboardChartGetterURL.replace('_FROM_DATE_', today).replace('_TO_DATE_', today) + selectedTourOperator)
      .done(function(data) {
        data['bookingCount'].map((item) => {
          labels.push(item.name);
          countData.push(item.tourCount);
        });
        if (window.myChart != undefined)
          window.myChart.destroy();
        var donutChartCanvas = $('#donutChart').get(0).getContext('2d')
        var donutData = {
          labels: labels,
          datasets: [{
            data: countData,
            backgroundColor: ['#f56954', '#00a65a', '#f39c12', '#00c0ef', '#3c8dbc', '#d2d6de'],
          }]
        }
        var donutOptions = {
          maintainAspectRatio: false,
          responsive: true,
        }
        //Create pie or douhnut chart
        // You can switch between pie and douhnut using the method below.
        window.myChart = new Chart(donutChartCanvas, {
          type: 'bar',
          data: donutData,
          options: donutOptions
        });

        var donutOptions = {
          // maintainAspectRatio : false,
          // responsive : true,
          scales: {
            yAxes: [{
              display: true,
              ticks: {
                beginAtZero: true
              }
            }]
          },
          legend: {
            display: false
          },
          title: {
            display: false,
            text: ""
          }
        }
        //Create pie or douhnut chart
        // You can switch between pie and douhnut using the method below.
        window.myChart = new Chart(donutChartCanvas, {
          type: 'bar',
          data: donutData,
          options: donutOptions
        });

        // let lineChart = new uPlot(optsLineChart, data, document.getElementById('lineChart'));

        window.addEventListener("resize", e => {

          //lineChart.setSize(getSize('lineChart'));
        });
      });

    // Start of Sales chart

    var lineLabels = [];
    var chartData = [];
    var packageName = "";
    var linesOfChart = [];
    var newData;
    let week = [];
    let currentArrayWeek = [];
    let check = [];

    $.getJSON(dashboardLineChartGetterURL.replace('_FROM_DATE_', today).replace('_TO_DATE_', today) + selectedTourOperator)
      .done(function(data) {
        if (data) {
          for (var main in data) {
            let colorGetter = (Math.random() * 0xfffff * 1000000).toString(16);
            let colorFormatter = '#' + colorGetter.slice(0, 6);
            var startDateValue = moment(today);
            var endDateValue = moment(today);
            var diffDays = endDateValue.diff(startDateValue, 'days');

            var test = 0;
            for (var children in data[main]) {
              packageName = data[main][children].name;
              if (diffDays == 30 || diffDays == 31 || diffDays == 28 || diffDays == 29) {
                let weekNumber = Math.floor((data[main][children].date - 1) / 7) + 1; // To give week number
                week.push("Week " + weekNumber);
              } else if (diffDays == 365 || diffDays == 366 || diffDays == 364) {
                // let weekNumber = Math.floor((data[main][children].date - 1) / 7) + 1; // To give week number
                week.push(data[main][children].date);
              } else {
                week.push(data[main][children].date);
              }
              currentArrayWeek.push(data[main][children].date);

              week.map((item) => {
                if (!currentArrayWeek.includes(item)) {
                  if (!check.includes(item)) {
                    chartData.push(0);
                  }
                  check.push(item);
                } else {
                  if (diffDays == 30 || diffDays == 31 || diffDays == 28 || diffDays == 29) {
                    let weekNumber = Math.floor((data[main][children].date - 1) / 7) + 1; // To give week number
                    if (item == "Week " + weekNumber) {
                      chartData.push(parseInt(data[main][children].totalTourists));
                    }
                  } else if (diffDays == 365 || diffDays == 366 || diffDays == 364) {
                    if (item == data[main][children].date) {
                      chartData.push(parseInt(data[main][children].totalTourists));
                    }
                  } else {
                    if (item == data[main][children].date) {
                      chartData.push(parseInt(data[main][children].totalTourists));
                    }
                  }
                }
              })
              test++;
            }


            // Lines of line chart
            newData = {
              label: packageName,
              fill: false,
              borderWidth: 2,
              lineTension: 0,
              spanGaps: true,
              borderColor: colorFormatter,
              pointRadius: 3,
              pointHoverRadius: 7,
              pointColor: colorFormatter,
              pointBackgroundColor: colorFormatter,
              data: chartData.filter((v, i, a) => a.indexOf(v) === i),
            };

            linesOfChart.push(newData);
            chartData = [];
            currentArrayWeek = [];
          }
          var salesGraphChartCanvas = $('#line-chart').get(0).getContext('2d')

          if (window.salesGraphChart != undefined)
            window.salesGraphChart.destroy();
          var salesGraphChartData = {
            labels: week.filter((v, i, a) => a.indexOf(v) === i),
            datasets: linesOfChart
          }


          var salesGraphChartOptions = {
            maintainAspectRatio: false,
            responsive: true,
            legend: {
              display: true
            },
            scales: {
              xAxes: [{
                ticks: {
                  fontColor: '#000'
                },
                gridLines: {
                  display: false,
                  color: '#000',
                  drawBorder: false
                }
              }],
              yAxes: [{
                ticks: {
                  stepSize: 10,
                  fontColor: '#000'
                },
                // gridLines: {
                //   display: true,
                //   color: '#000',
                //   drawBorder: false
                // }
              }]
            }
          }

          window.salesGraphChart = new Chart(salesGraphChartCanvas, { // lgtm[js/unused-local-variable]
            type: 'line',
            data: salesGraphChartData,
            options: salesGraphChartOptions
          })

        }
      });
    // End of Sales chart
  });
</script>
@endsection
@endif