package com.hazraelectricalbike.app

import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import androidx.core.content.FileProvider
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel
import java.io.File
import java.net.HttpURLConnection
import java.net.URL
import java.security.MessageDigest
import java.util.concurrent.Executors

class MainActivity : FlutterActivity() {
    private val worker = Executors.newSingleThreadExecutor()
    private val apk by lazy { File(filesDir, "updates/update.apk") }
    private var downloading = false

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        val channel = MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "hazra_ev/updater")
        channel.setMethodCallHandler { call, result ->
            when (call.method) {
                "sdk" -> result.success(Build.VERSION.SDK_INT)
                "canInstall" -> result.success(Build.VERSION.SDK_INT < 26 || packageManager.canRequestPackageInstalls())
                "download" -> {
                    if (downloading) {
                        result.error("BUSY", "An update is already downloading.", null)
                        return@setMethodCallHandler
                    }
                    val url = call.argument<String>("url")!!
                    val token = call.argument<String>("token")!!
                    val checksum = call.argument<String>("checksum")!!
                    val size = call.argument<Number>("size")!!.toLong()
                    val version = call.argument<Number>("version")!!.toLong()
                    downloading = true
                    worker.execute {
                        try {
                            require(checksum.matches(Regex("[a-fA-F0-9]{64}")) && size > 0)
                            apk.parentFile!!.mkdirs()
                            if (!validApk(checksum, size, version)) {
                                apk.delete()
                                val partial = File(apk.parentFile, "download.part")
                                val connection = URL(url).openConnection() as HttpURLConnection
                                connection.instanceFollowRedirects = false
                                connection.connectTimeout = 30000
                                connection.readTimeout = 60000
                                connection.setRequestProperty("Authorization", "Bearer $token")
                                try {
                                    require(connection.responseCode == 200) { "Download failed (${connection.responseCode}). Try again." }
                                    var received = 0L
                                    var lastProgress = -1
                                    connection.inputStream.use { input ->
                                        partial.outputStream().use { output ->
                                            val buffer = ByteArray(64 * 1024)
                                            while (true) {
                                                val count = input.read(buffer)
                                                if (count < 0) break
                                                received += count
                                                require(received <= size) { "Update size does not match." }
                                                output.write(buffer, 0, count)
                                                val progress = (received * 100 / size).toInt()
                                                if (progress != lastProgress) {
                                                    lastProgress = progress
                                                    runOnUiThread { channel.invokeMethod("progress", progress) }
                                                }
                                            }
                                        }
                                    }
                                    require(received == size && partial.renameTo(apk)) { "Download was incomplete. Try again." }
                                } finally {
                                    connection.disconnect()
                                    partial.delete()
                                }
                                require(validApk(checksum, size, version)) { "The update could not be verified. Contact your administrator." }
                            }
                            runOnUiThread { downloading = false; result.success(null) }
                        } catch (e: Exception) {
                            apk.delete()
                            runOnUiThread { downloading = false; result.error("DOWNLOAD_FAILED", e.message, null) }
                        }
                    }
                }
                "install" -> {
                    try {
                        require(apk.isFile) { "Download the update again." }
                        if (Build.VERSION.SDK_INT >= 26 && !packageManager.canRequestPackageInstalls()) {
                            startActivity(Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES, Uri.parse("package:$packageName")))
                            result.success(false)
                        } else {
                            val uri = FileProvider.getUriForFile(this, "$packageName.updates", apk)
                            startActivity(Intent(Intent.ACTION_VIEW).apply {
                                setDataAndType(uri, "application/vnd.android.package-archive")
                                addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION)
                            })
                            result.success(true)
                        }
                    } catch (e: Exception) {
                        result.error("INSTALL_FAILED", e.message, null)
                    }
                }
                else -> result.notImplemented()
            }
        }
    }

    @Suppress("DEPRECATION")
    private fun validApk(checksum: String, size: Long, version: Long): Boolean {
        if (!apk.isFile || apk.length() != size) return false
        val digest = MessageDigest.getInstance("SHA-256")
        apk.inputStream().use { input ->
            val buffer = ByteArray(64 * 1024)
            while (true) {
                val count = input.read(buffer)
                if (count < 0) break
                digest.update(buffer, 0, count)
            }
        }
        if (!digest.digest().joinToString("") { "%02x".format(it) }.equals(checksum, true)) return false
        val info = packageManager.getPackageArchiveInfo(apk.path, 0) ?: return false
        val code = if (Build.VERSION.SDK_INT >= 28) info.longVersionCode else info.versionCode.toLong()
        val installed = packageManager.getPackageInfo(packageName, 0)
        val installedCode = if (Build.VERSION.SDK_INT >= 28) installed.longVersionCode else installed.versionCode.toLong()
        return info.packageName == packageName && code == version && code > installedCode &&
            (Build.VERSION.SDK_INT < 24 || (info.applicationInfo?.minSdkVersion ?: 0) <= Build.VERSION.SDK_INT)
    }
}
