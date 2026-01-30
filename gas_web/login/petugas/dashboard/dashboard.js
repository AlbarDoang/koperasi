/* globals Chart:false */

(() => {
  "use strict";

  // Graphs (only initialize if canvas exists)
  const chartEl = document.getElementById("myChart");
  if (chartEl) {
    // eslint-disable-next-line no-unused-vars
    const myChart = new Chart(chartEl, {
      type: "line",
      data: {
        labels: [
          "Sunday",
          "Monday",
          "Tuesday",
          "Wednesday",
          "Thursday",
          "Friday",
          "Saturday",
        ],
        datasets: [
          {
            data: [15339, 21345, 18483, 24003, 23489, 24092, 12034],
            lineTension: 0,
            backgroundColor: "transparent",
            borderColor: "#007bff",
            borderWidth: 4,
            pointBackgroundColor: "#007bff",
          },
        ],
      },
      options: {
        plugins: {
          legend: {
            display: false,
          },
          tooltip: {
            boxPadding: 3,
          },
        },
      },
    });
  }
})();
