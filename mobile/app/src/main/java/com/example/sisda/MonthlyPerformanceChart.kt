package com.example.sisda

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.unit.dp
import com.example.sisda.ui.theme.Amber600
import com.example.sisda.ui.theme.Emerald600

private val chartData = listOf(
    ChartData("Jan", 45, 32),
    ChartData("Feb", 52, 41),
    ChartData("Mac", 48, 38),
    ChartData("Apr", 61, 45),
    ChartData("Mei", 55, 52),
    ChartData("Jun", 67, 48),
)

private val maxValue = chartData.flatMap { listOf(it.debit, it.credit) }.maxOrNull() ?: 0

@Composable
fun MonthlyPerformanceChart() {
    Card(
        modifier = Modifier
            .fillMaxWidth()
            .padding(8.dp),
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            Text("Prestasi Bulanan", style = MaterialTheme.typography.titleMedium)
            Spacer(modifier = Modifier.height(16.dp))
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceAround,
                verticalAlignment = Alignment.Bottom
            ) {
                chartData.forEach { data ->
                    Column(
                        horizontalAlignment = Alignment.CenterHorizontally
                    ) {
                        Row(
                            modifier = Modifier.height(150.dp),
                            verticalAlignment = Alignment.Bottom
                        ) {
                            Box(
                                modifier = Modifier
                                    .weight(1f)
                                    .fillMaxHeight(data.debit.toFloat() / maxValue)
                                    .clip(RoundedCornerShape(topStart = 4.dp, topEnd = 4.dp))
                                    .background(Amber600)
                            )
                            Spacer(modifier = Modifier.width(4.dp))
                            Box(
                                modifier = Modifier
                                    .weight(1f)
                                    .fillMaxHeight(data.credit.toFloat() / maxValue)
                                    .clip(RoundedCornerShape(topStart = 4.dp, topEnd = 4.dp))
                                    .background(Emerald600)
                            )
                        }
                        Spacer(modifier = Modifier.height(4.dp))
                        Text(data.month, style = MaterialTheme.typography.bodySmall)
                    }
                }
            }
        }
    }
}

private data class ChartData(val month: String, val debit: Int, val credit: Int)
