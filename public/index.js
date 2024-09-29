document.getElementById('get-info').addEventListener("click", function() {
    const apiKey = document.getElementById('api-key').value;
    const apiUrl = `http://127.0.0.1:3000/crawl?url=https://www.klick.ee&api_key=${apiKey}`;

    console.log(apiUrl)

    // Clear previous error message
    document.getElementById('error-message').textContent = '';

    // Fetch the data from the PHP API with the provided API key
    fetch(apiUrl)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                // Display error if the API key is invalid
                document.getElementById('error-message').textContent = data.error;
            } else {
                // Call functions to process and display the data in charts
                createCategoryPieChart(data);
                createPriceRangeBarChart(data);
            }
        })
        .catch(error => {
            document.getElementById('error-message').textContent = 'Error fetching data: ' + error;
        });
});

function createCategoryPieChart(data) {
    const categoryNames = [];
    const productCounts = [];
    
    data.forEach(category => {
        categoryNames.push(category.category);
        productCounts.push(category.products.length);
    });
    
    const ctx = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        outerHeight: 400,
        outerWidth: 400,
        data: {
            labels: categoryNames,
            datasets: [{
                label: 'Products per Category',
                data: productCounts,
                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#96f04c', '#ab7f68']
            }]
        }
    });
}

function createPriceRangeBarChart(data) {
    const priceRanges = { '0-100': 0, '100-500': 0, '500-1000': 0, '1000+': 0 };

    data.forEach(category => {
        category.products.forEach(product => {
            const price = product.price;
            if (price <= 100) {
                priceRanges['0-100']++;
            } else if (price <= 500) {
                priceRanges['100-500']++;
            } else if (price <= 1000) {
                priceRanges['500-1000']++;
            } else {
                priceRanges['1000+']++;
            }
        });
    });

    const ctx = document.getElementById('priceRangeChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: Object.keys(priceRanges),
            datasets: [{
                data: Object.values(priceRanges),
                backgroundColor: '#36A2EB'
            }]
        }
    });
}