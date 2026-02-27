package com.example.sisda

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.grid.GridCells
import androidx.compose.foundation.lazy.grid.LazyVerticalGrid
import androidx.compose.foundation.lazy.grid.items
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountBalance
import androidx.compose.material.icons.filled.AccountCircle
import androidx.compose.material.icons.filled.DateRange
import androidx.compose.material.icons.filled.Home
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import com.example.sisda.ui.theme.*

@Composable
fun DashboardScreen() {
    val metrics = listOf(
        Metric("Jumlah Pengguna", "1,234", "+12.5%", "increase", Icons.Default.AccountCircle, Emerald600),
        Metric("Data Aktif", "856", "+8.2%", "increase", Icons.Default.Home, Sky600),
        Metric("Aktiviti Bulanan", "3,456", "+23.1%", "increase", Icons.Default.DateRange, Amber600),
        Metric("Pertumbuhan", "94%", "+4.3%", "increase", Icons.Default.AccountBalance, Rose600)
    )

    LazyVerticalGrid(
        columns = GridCells.Adaptive(minSize = 150.dp),
        modifier = Modifier.fillMaxSize(),
        contentPadding = PaddingValues(16.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp),
        horizontalArrangement = Arrangement.spacedBy(16.dp)
    ) {
        item {
            Column {
                Text("Dashboard", style = MaterialTheme.typography.titleLarge)
                Spacer(modifier = Modifier.height(4.dp))
                Text("Selamat datang ke panel kawalan SISDA", style = MaterialTheme.typography.bodyMedium)
            }
        }

        items(metrics) { metric ->
            MetricCard(metric.name, metric.value, metric.change, metric.changeType, metric.icon, metric.color)
        }

        item {
            MonthlyPerformanceChart()
        }

        item {
            RecentActivityTable()
        }
    }
}

data class Metric(val name: String, val value: String, val change: String, val changeType: String, val icon: androidx.compose.ui.graphics.vector.ImageVector, val color: androidx.compose.ui.graphics.Color)
