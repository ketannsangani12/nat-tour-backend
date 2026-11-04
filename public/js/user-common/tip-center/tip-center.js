class TipCenter {
    constructor(props) {
        this.tipAmountsOfToday = props.tipAmountsOfToday || [];
        this.tipAmountsOfWeek = props.tipAmountsOfWeek || [];
        this.tipAmountsOfMonth = props.tipAmountsOfMonth || [];
        this.tipAmountsOfYear = props.tipAmountsOfYear || [];

        this.initializeCharts();
    }

    initializeCharts() {
        this.createBarChart(
            '#barChartToday',
            this.tipAmountsOfToday,
            ['#f56954', '#00a65a', '#f39c12', '#00c0ef', '#3c8dbc', '#d2d6de'],
            ['#c0392b', '#27ae60', '#e67e22', '#16a085', '#2980b9', '#95a5a6']
        );
        this.createBarChart(
            '#barChartWeek',
            this.tipAmountsOfWeek,
            ['#cf0c6a', '#9a56c7', '#bd6d4d'],
            ['#cf0c6a', '#9a56c7', '#bd6d4d']
        );
        this.createBarChart(
            '#barChartMonth',
            this.tipAmountsOfMonth,
            ['#a38e48', '#3c8dbc', '#d2d6de'],
            ['#a38e48', '#3c8dbc', '#d2d6de']
        );
        this.createBarChart(
            '#barChartYear',
            this.tipAmountsOfYear,
            ['#827b57', '#6b67a3', '#a36787'],
            ['#827b57', '#6b67a3', '#a36787']
        );
    }

    createBarChart(canvasSelector, dataSet, colors, hoverColors) {
        let labels, amounts;
    
        if (!dataSet || dataSet.length === 0) {
            // Show placeholder bar with 0
            labels = ['No Data'];
            amounts = [0];
        } else {
            labels = dataSet.map(item => item.tipType + ' Tips');
            amounts = dataSet.map(item => item.totalTipAmount);
        }
    
        const chartCanvas = $(canvasSelector).get(0).getContext('2d');
    
        const barData = {
            labels: labels,
            datasets: [{
                label: 'Tips',
                data: amounts,
                backgroundColor: colors,
                hoverBackgroundColor: hoverColors,
                borderWidth: 1
            }]
        };
    
        const barOptions = {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 0 },
            hover: { animationDuration: 0 },
            scales: {
                yAxes: [{
                    ticks: { beginAtZero: true }
                }]
            },
            legend: { display: false },
            title: { display: false }
        };
    
        new Chart(chartCanvas, {
            type: 'bar',
            data: barData,
            options: barOptions
        });
    }
    
}
