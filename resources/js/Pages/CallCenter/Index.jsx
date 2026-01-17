import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import {
    Phone,
    PhoneCall,
    PhoneIncoming,
    PhoneOutgoing,
    Users,
    BarChart3,
    MessageSquare,
    TrendingUp,
    Clock,
    CheckCircle2,
    AlertCircle,
    Database,
    FileText,
    Headphones,
    Activity,
    Zap
} from 'lucide-react';

export default function Index() {
    const features = [
        {
            icon: PhoneCall,
            title: 'Automated Call Handling',
            description: 'Intelligent call routing and automated dialing system for efficient voter outreach',
            color: 'blue'
        },
        {
            icon: MessageSquare,
            title: 'Voter Interaction Logging',
            description: 'Comprehensive recording and tracking of all voter communications and responses',
            color: 'emerald'
        },
        {
            icon: TrendingUp,
            title: 'Sentiment & Issue Tracking',
            description: 'Real-time analysis of voter sentiment and key political issues identification',
            color: 'amber'
        },
        {
            icon: BarChart3,
            title: 'Call Analytics & Reporting',
            description: 'Detailed analytics dashboard with performance metrics and insights',
            color: 'purple'
        },
        {
            icon: Database,
            title: 'Voter Database Integration',
            description: 'Seamless integration with existing voter registration and demographic data',
            color: 'sky'
        },
        {
            icon: Headphones,
            title: 'Agent Management',
            description: 'Monitor agent performance, call quality, and productivity metrics',
            color: 'rose'
        }
    ];

    const stats = [
        { label: 'Total Calls', value: '---', icon: Phone, color: 'blue' },
        { label: 'Active Agents', value: '---', icon: Users, color: 'emerald' },
        { label: 'Avg Call Duration', value: '---', icon: Clock, color: 'amber' },
        { label: 'Success Rate', value: '---', icon: CheckCircle2, color: 'purple' }
    ];

    const getColorClasses = (color) => {
        const colors = {
            blue: 'bg-blue-50 text-blue-600 border-blue-100',
            emerald: 'bg-emerald-50 text-emerald-600 border-emerald-100',
            amber: 'bg-amber-50 text-amber-600 border-amber-100',
            purple: 'bg-purple-50 text-purple-600 border-purple-100',
            sky: 'bg-sky-50 text-sky-600 border-sky-100',
            rose: 'bg-rose-50 text-rose-600 border-rose-100'
        };
        return colors[color] || colors.blue;
    };

    return (
        <AuthenticatedLayout>
            <Head title="Call Center" />

            <div className="space-y-6">
                {/* Header Section */}
                <div className="bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl p-8 text-white shadow-lg">
                    <div className="flex items-center justify-between">
                        <div className="space-y-2">
                            <div className="flex items-center space-x-3">
                                <div className="p-3 bg-white/10 backdrop-blur-sm rounded-lg">
                                    <Phone className="h-8 w-8" />
                                </div>
                                <div>
                                    <h1 className="text-3xl font-bold">Call Center</h1>
                                    <p className="text-blue-100 text-sm">Political Communication & Analytics Module</p>
                                </div>
                            </div>
                        </div>
                        <div className="hidden md:flex items-center space-x-2 px-4 py-2 bg-white/10 backdrop-blur-sm rounded-lg border border-white/20">
                            <div className="h-2 w-2 bg-amber-400 rounded-full animate-pulse"></div>
                            <span className="text-sm font-medium">Under Development</span>
                        </div>
                    </div>
                </div>

                {/* Status Card */}
                <div className="bg-gradient-to-br from-amber-50 to-orange-50 border-2 border-amber-200 rounded-xl p-6">
                    <div className="flex items-start space-x-4">
                        <div className="flex-shrink-0">
                            <div className="p-3 bg-amber-100 rounded-lg">
                                <AlertCircle className="h-6 w-6 text-amber-600" />
                            </div>
                        </div>
                        <div className="flex-1">
                            <h3 className="text-lg font-semibold text-amber-900 mb-2">
                                Module Under Development
                            </h3>
                            <p className="text-amber-800 leading-relaxed">
                                We are currently developing a comprehensive Call Center system designed specifically for political organizations.
                                This module will enable efficient voter outreach, sentiment analysis, and data-driven campaign strategies.
                            </p>
                        </div>
                    </div>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    {stats.map((stat, index) => (
                        <div key={index} className="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition-shadow">
                            <div className="flex items-center justify-between mb-4">
                                <div className={`p-3 rounded-lg ${getColorClasses(stat.color)}`}>
                                    <stat.icon className="h-5 w-5" />
                                </div>
                            </div>
                            <div>
                                <p className="text-sm text-slate-600 mb-1">{stat.label}</p>
                                <p className="text-2xl font-bold text-slate-900">{stat.value}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Features Grid */}
                <div>
                    <div className="mb-6">
                        <h2 className="text-xl font-bold text-slate-900 mb-2">Planned Features</h2>
                        <p className="text-slate-600">Comprehensive tools for political communication and voter engagement</p>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {features.map((feature, index) => (
                            <div
                                key={index}
                                className="bg-white rounded-xl border border-slate-200 p-6 hover:border-blue-300 hover:shadow-lg transition-all duration-300 group"
                            >
                                <div className={`inline-flex p-3 rounded-lg mb-4 ${getColorClasses(feature.color)} group-hover:scale-110 transition-transform`}>
                                    <feature.icon className="h-6 w-6" />
                                </div>
                                <h3 className="text-lg font-semibold text-slate-900 mb-2">
                                    {feature.title}
                                </h3>
                                <p className="text-slate-600 text-sm leading-relaxed">
                                    {feature.description}
                                </p>
                            </div>
                        ))}
                    </div>
                </div>

                {/* System Capabilities */}
                <div className="bg-white rounded-xl border border-slate-200 p-8 shadow-sm">
                    <div className="flex items-start space-x-4 mb-6">
                        <div className="p-3 bg-blue-50 rounded-lg">
                            <Activity className="h-6 w-6 text-blue-600" />
                        </div>
                        <div>
                            <h2 className="text-xl font-bold text-slate-900 mb-2">System Capabilities</h2>
                            <p className="text-slate-600">Advanced features for political campaign management</p>
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="space-y-4">
                            <div className="flex items-start space-x-3">
                                <PhoneOutgoing className="h-5 w-5 text-blue-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <h4 className="font-semibold text-slate-900 mb-1">Outbound Campaigns</h4>
                                    <p className="text-sm text-slate-600">Automated dialing for voter surveys and campaign messaging</p>
                                </div>
                            </div>
                            <div className="flex items-start space-x-3">
                                <PhoneIncoming className="h-5 w-5 text-emerald-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <h4 className="font-semibold text-slate-900 mb-1">Inbound Support</h4>
                                    <p className="text-sm text-slate-600">Handle voter inquiries and feedback efficiently</p>
                                </div>
                            </div>
                            <div className="flex items-start space-x-3">
                                <FileText className="h-5 w-5 text-purple-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <h4 className="font-semibold text-slate-900 mb-1">Call Scripting</h4>
                                    <p className="text-sm text-slate-600">Dynamic scripts based on voter demographics and issues</p>
                                </div>
                            </div>
                        </div>

                        <div className="space-y-4">
                            <div className="flex items-start space-x-3">
                                <BarChart3 className="h-5 w-5 text-amber-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <h4 className="font-semibold text-slate-900 mb-1">Real-time Analytics</h4>
                                    <p className="text-sm text-slate-600">Live dashboards with call metrics and performance indicators</p>
                                </div>
                            </div>
                            <div className="flex items-start space-x-3">
                                <Database className="h-5 w-5 text-sky-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <h4 className="font-semibold text-slate-900 mb-1">Data Integration</h4>
                                    <p className="text-sm text-slate-600">Seamless sync with voter registration and demographic databases</p>
                                </div>
                            </div>
                            <div className="flex items-start space-x-3">
                                <Zap className="h-5 w-5 text-rose-600 mt-0.5 flex-shrink-0" />
                                <div>
                                    <h4 className="font-semibold text-slate-900 mb-1">AI-Powered Insights</h4>
                                    <p className="text-sm text-slate-600">Sentiment analysis and predictive voter behavior modeling</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Coming Soon Footer */}
                <div className="bg-gradient-to-r from-slate-50 to-slate-100 rounded-xl border border-slate-200 p-6 text-center">
                    <div className="inline-flex items-center justify-center space-x-2 mb-3">
                        <Clock className="h-5 w-5 text-slate-600" />
                        <span className="text-lg font-semibold text-slate-900">Coming Soon</span>
                    </div>
                    <p className="text-slate-600 max-w-2xl mx-auto">
                        Our development team is working diligently to bring you a state-of-the-art Call Center solution.
                        Stay tuned for updates on the launch timeline.
                    </p>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
