# Add project specific ProGuard rules here.
# By default, the flags in this file are appended to flags specified
# in /Users/Shared/Android/sdk/tools/proguard/proguard-android.txt
# You can edit that file to add rules that are common to all your projects.
#
# For more details, see
#   http://developer.android.com/guide/developing/tools/proguard.html

# Add any project specific keep options here:

# If you use reflection to access classes in common ways, for example to
# create instances of custom Views, you may need this.
# -keep public class * extends android.view.View {
#    public <init>(android.content.Context);
#    public <init>(android.content.Context, android.util.AttributeSet);
#    public <init>(android.content.Context, android.util.AttributeSet, int);
#    public void set*(...);
# }

-keepattributes *Annotation*
-keepclassmembers class ** {
    @android.webkit.JavascriptInterface <methods>;
}

-dontwarn org.conscrypt.**
-dontwarn org.bouncycastle.**
-dontwarn okhttp3.**
-dontwarn okio.**
-dontwarn org.jetbrains.annotations.**
-dontwarn kotlin.Metadata
-dontwarn kotlin.jvm.internal.Intrinsics
-dontwarn kotlinx.coroutines.debug.**
-dontwarn com.google.errorprone.annotations.**
-dontwarn javax.annotation.**
-dontwarn org.codehaus.mojo.animal_sniffer.IgnoreJRERequirement
-dontwarn org.junit.**

-keep class kotlin.coroutines.jvm.internal.DebugMetadataKt

-assumenosideeffects class android.util.Log {
    public static *** d(...);
    public static *** v(...);
}
