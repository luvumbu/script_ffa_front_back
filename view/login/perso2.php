 
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.js"></script>
 

<canvas id="myChart" style="width:100%;max-width:600px"></canvas>

<script>
const xValues = ["aout 2021", "Jan 2000", "Septembre 2020"];
const yValues = [55, 49, 44, 24, 15];
const barColors = ["#d1d2d4", "#d1d2d4","#d1d2d4"];

new Chart("myChart", {
  type: "bar",
  data: {
    labels: xValues,
    datasets: [{
      backgroundColor: barColors,
      data: yValues
    }]
  },
  options: {
    legend: {display: false},
    title: {
      display: true,
      text: "MES 3 MEILLEURES PERFORMANCES"
    }
  }
});
</script>

 