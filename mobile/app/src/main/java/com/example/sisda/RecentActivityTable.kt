package com.example.sisda

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.Card
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp
import com.example.sisda.ui.theme.Amber100
import com.example.sisda.ui.theme.Amber800
import com.example.sisda.ui.theme.Emerald100
import com.example.sisda.ui.theme.Emerald800
import com.example.sisda.ui.theme.Slate100
import com.example.sisda.ui.theme.Slate800

private val recentData = listOf(
    RecentActivity("Ahmad bin Ali", "Aktif", "2025-11-19", "RM 1,234"),
    RecentActivity("Siti Nurhaliza", "Aktif", "2025-11-18", "RM 2,456"),
    RecentActivity("Muhammad Hafiz", "Pending", "2025-11-17", "RM 890"),
    RecentActivity("Nurul Ain", "Aktif", "2025-11-16", "RM 3,210"),
    RecentActivity("Khairul Anuar", "Tidak Aktif", "2025-11-15", "RM 567"),
)

@Composable
fun RecentActivityTable() {
    Card(
        modifier = Modifier.fillMaxWidth(),
        shape = RoundedCornerShape(12.dp)
    ) {
        Column(
            modifier = Modifier.padding(16.dp)
        ) {
            Row(
                modifier = Modifier.fillMaxWidth(),
                horizontalArrangement = Arrangement.SpaceBetween,
                verticalAlignment = Alignment.CenterVertically
            ) {
                Text("Aktiviti Terkini", style = MaterialTheme.typography.titleMedium)
                Text("Lihat Semua", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.primary)
            }
            Spacer(modifier = Modifier.height(16.dp))
            LazyColumn {
                items(recentData) { activity ->
                    Row(
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(vertical = 12.dp),
                        horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically
                    ) {
                        Column(modifier = Modifier.weight(1f)) {
                            Text(activity.name, style = MaterialTheme.typography.bodyLarge)
                            Text(activity.date, style = MaterialTheme.typography.bodySmall)
                        }
                        Column(horizontalAlignment = Alignment.End) {
                            Text(activity.value, style = MaterialTheme.typography.bodyLarge)
                            StatusBadge(activity.status)
                        }
                    }
                }
            }
        }
    }
}

@Composable
fun StatusBadge(status: String) {
    val (backgroundColor, textColor) = when (status) {
        "Aktif" -> Emerald100 to Emerald800
        "Pending" -> Amber100 to Amber800
        else -> Slate100 to Slate800
    }

    Box(
        modifier = Modifier
            .clip(RoundedCornerShape(12.dp))
            .background(backgroundColor)
            .padding(horizontal = 8.dp, vertical = 4.dp)
    ) {
        Text(status, color = textColor, style = MaterialTheme.typography.labelSmall)
    }
}

private data class RecentActivity(val name: String, val status: String, val date: String, val value: String)
