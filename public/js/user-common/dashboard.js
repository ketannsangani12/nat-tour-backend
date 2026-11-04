$(function () {
  'use strict'
  var dashboardLineChartGetter;

  $('#daterange').daterangepicker({
    ranges: {
      Today: [moment(), moment()],
      'This Week': [moment().startOf('isoweek'), moment().endOf('isoweek')],
      'This Month': [moment().startOf('month'), moment().endOf('month')],
      'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
      'This Year': [moment().startOf('year'), moment().endOf('year')],
      'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
    },
    startDate: moment(),
    endDate: moment()
  }, function (start, end, label) {
    $('#selectedChartOption').text(label);
    var selectedTourOperator = $('#tour_operator').val();
    if(selectedTourOperator == "") {
      selectedTourOperator = $('#tour_operator').val($('#tour_operator option:first').val());
    }
  

    var labels = [];
    var countData = [];
    var startDate = start.format('YYYY-MM-DD');
    var endDate = end.format('YYYY-MM-DD');

    $.getJSON(dashboardChartGetterURL.replace('_FROM_DATE_', startDate).replace('_TO_DATE_', endDate) + selectedTourOperator)
    .done(function(data) {
      data['bookingCount'].map((item) => {
        labels.push(item.name);
        countData.push(item.tourCount);
      });

      if(data.totalAmount[0].totalAmount !== "") {
        $('#totalAmount').text(parseFloat(data.totalAmount[0].totalAmount));  
      } else {
        $('#totalAmount').text(0);
      }
      
      if(data.totalOrder[0].totalOrder !== "") {
        $('#totalOrder').text(data.totalOrder[0].totalOrder);
      } else {
        $('#totalOrder').text(0);
      }

      if(data.totalCustomer[0].totalCustomers !== "") {
        $('#totalCustomer').text(data.totalCustomer[0].totalCustomers);
      } else {
        $('#totalCustomer').text(0);
      }

      var ctxLine = document.getElementById("donutChart").getContext("2d");
      if(window.myChart != undefined) 
        window.myChart.destroy(); 
      window.myChart = new Chart(ctxLine, {});

      var donutChartCanvas = $('#donutChart').get(0).getContext('2d')
      var donutData        = {
        labels: labels,
        datasets: [
        {
          data: countData,
          backgroundColor : ['#f56954', '#00a65a', '#f39c12', '#00c0ef', '#3c8dbc', '#d2d6de'],
        }
        ]
      }
      var donutOptions     = {
        scales: {
          yAxes: [{
                display: true,
                ticks: {
                    beginAtZero: true
                }
            }]
        },
        legend: {display: false},
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

    // Start of Sales chart

    var lineLabels = [];
    var chartData = [];
    var packageName = "";
    var linesOfChart = [];
    var newData;
    let week = [];
    let currentArrayWeek = [];
    let justCheck = [];
    let check = [];
    let newDataCheck = false;
    let week1 = 0;
    let week2 = 0;
    let week3 = 0;
    let week4 = 0;
    let week5 = 0;
    let january = 0;
    let february = 0;
    let march = 0;
    let april = 0;
    let may = 0;
    let june = 0;
    let july = 0;
    let august = 0;
    let september = 0;
    let october = 0;
    let november = 0;
    let december = 0;

    $.getJSON(dashboardLineChartGetterURL.replace('_FROM_DATE_', startDate).replace('_TO_DATE_', endDate) + selectedTourOperator)
    .done(function(data) {
      if(data) {
        for(var main in data) {
          let colorGetter = (Math.random() * 0xfffff * 1000000).toString(16);
          let colorFormatter = '#' + colorGetter.slice(0, 6);
          var startDateValue = moment(startDate);
          var endDateValue = moment(endDate);
          var diffDays = endDateValue.diff(startDateValue, 'days');

          var test = 0;

          for(var children in data[main]) {

            packageName = data[main][children].name;
            if(diffDays == 30 || diffDays == 31 || diffDays == 27 || diffDays == 28 || diffDays == 29) {
              let weekNumber = Math.floor((data[main][children].date - 1) / 7) + 1; // To give week number
             
              week = ["Week 1", "Week 2", "Week 3", "Week 4","Week 5"];
              currentArrayWeek.push("Week " + weekNumber);
              data[main].map((item) => {
                justCheck.push("Week " + weekNumber); 
              })
            } else if(diffDays == 365 || diffDays == 366 || diffDays == 364) {
              week = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
              currentArrayWeek.push(data[main][children].date);
              data[main].map((item) => {
                justCheck.push(item.date); 
              })
            } else {
             week.push(data[main][children].date);
             currentArrayWeek.push(data[main][children].date);
             data[main].map((item) => {
              justCheck.push(item.date); 
            })
           }

           const uniqueWeek = week.filter((v,i,a)  => a.indexOf(v) === i );

           uniqueWeek.map((item) => {
            if(!justCheck.includes(item)) {
              if(!check.includes(item)) {
                chartData.push(0);
              }
              check.push(item);
              
            } else {
              if(diffDays == 30 || diffDays == 31 || diffDays == 27 || diffDays == 28 || diffDays == 29) {
                  let weekNumber = Math.floor((data[main][children].date - 1) / 7) + 1; // To give week number
                  if(item == "Week " + weekNumber) {
                    if(item == "Week 1") {
                      week1 += parseInt(data[main][children].totalTourists);
                    } else if (item == "Week 2") {
                      week2 += parseInt(data[main][children].totalTourists);
                    } else if (item == "Week 3") {
                      week3 += parseInt(data[main][children].totalTourists);
                    } else if (item == "Week 4") {
                      week4 += parseInt(data[main][children].totalTourists);
                    } else if (item == "Week 5") {
                    week5 += parseInt(data[main][children].totalTourists);
                  }
                 
                    chartData = [week1, week2, week3, week4, week5];
                  }
                  newDataCheck = true;
                } else if(diffDays == 365 || diffDays == 366 || diffDays == 364) {
                 
                  if(item == data[main][children].date) {
                    if(item == "January") {
                      january += parseInt(data[main][children].totalTourists);
                    } else if (item == "February") {
                      february += parseInt(data[main][children].totalTourists);
                    } else if (item == "March") {
                      march += parseInt(data[main][children].totalTourists);
                    } else if (item == "April") {
                      april += parseInt(data[main][children].totalTourists);
                    } else if (item == "May") {
                      may += parseInt(data[main][children].totalTourists);
                    } else if (item == "June") {
                      june += parseInt(data[main][children].totalTourists);
                    } else if (item == "July") {
                      july += parseInt(data[main][children].totalTourists);
                    } else if (item == "August") {
                      august += parseInt(data[main][children].totalTourists);
                    } else if (item == "September") {
                      september += parseInt(data[main][children].totalTourists);
                    } else if (item == "October") {
                      october += parseInt(data[main][children].totalTourists);
                    } else if (item == "November") {
                      november += parseInt(data[main][children].totalTourists);
                    } else if (item == "December") {
                      december += parseInt(data[main][children].totalTourists);
                    }
                  }
                   
                    chartData = [january, february, march, april, may, june, july, august, september, october, november, december];

                    newDataCheck = true;
                  } else if (diffDays == 6) {
                    if(item == data[main][children].date) {
                      chartData.push(parseInt(data[main][children].totalTourists));   
                    }
                    newDataCheck = true;
                  } else {
                    if(item == data[main][children].date) {
                      chartData.push(parseInt(data[main][children].totalTourists));   
                    }
                    newDataCheck = false;
                  }
                  check.push(item);

                }
              })

           justCheck = [];
           test++;
         }

          // Lines of line chart
          if(newDataCheck == false) {
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
              data: chartData.filter((v,i,a) => a.indexOf(v) === i )
            };
          } else {
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
              data: chartData
            };
          }
          
          linesOfChart.push(newData);
          chartData = [];
          check = [];
          currentArrayWeek = [];
          week1 = 0;
          week2 = 0;
          week3 = 0;
          week4 = 0;
          january = 0;
          february = 0;
          march = 0;
          april = 0;
          may = 0;
          june = 0;
          july = 0;
          august = 0;
          september = 0;
          october = 0;
          november = 0;
          december = 0;
        }
        var salesGraphChartCanvas = $('#line-chart').get(0).getContext('2d')
 

  if(window.salesGraphChart != undefined) 
    window.salesGraphChart.destroy();

var salesGraphChartData = {
  labels: week.filter((v,i,a)  => a.indexOf(v) === i ),
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
})
});
