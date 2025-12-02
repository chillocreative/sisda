import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { Search, ClipboardList, UserCheck, Eye, Edit, X } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';

export default function UserDashboard() {
    const [searchQuery, setSearchQuery] = useState('');
    const [searchResults, setSearchResults] = useState([]);
    const [isSearching, setIsSearching] = useState(false);
    const [showResults, setShowResults] = useState(false);
    const [selectedRecord, setSelectedRecord] = useState(null);
    const [showModal, setShowModal] = useState(false);
    const searchRef = useRef(null);

    // Close dropdown when clicking outside
    useEffect(() => {
        function handleClickOutside(event) {
            if (searchRef.current && !searchRef.current.contains(event.target)) {
                setShowResults(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    // Search IC
    useEffect(() => {
        const delayDebounceFn = setTimeout(() => {
            if (searchQuery.length >= 3) {
                performSearch();
            } else {
                setSearchResults([]);
                setShowResults(false);
            }
        }, 300);

        return () => clearTimeout(delayDebounceFn);
    }, [searchQuery]);

    const performSearch = async () => {
        setIsSearching(true);
        try {
            const response = await axios.get(route('dashboard.search-ic'), {
                params: { ic: searchQuery }
            });
            setSearchResults(response.data);
            setShowResults(true);
        } catch (error) {
            console.error('Search error:', error);
        } finally {
            setIsSearching(false);
        }
    };

    const handleResultClick = (result) => {
        if (result.can_edit) {
            // Navigate to edit page
            router.visit(result.edit_url);
        } else {
            // Show view-only modal
            setSelectedRecord(result);
            setShowModal(true);
            setShowResults(false);
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="max-w-4xl mx-auto space-y-8">
                {/* Welcome Section */}
                <div className="text-center">
                    <h1 className="text-3xl font-bold text-slate-900">Selamat Datang ke SISDA</h1>
                    <p className="text-slate-600 mt-2">Sistem Maklumat Sumber Data</p>
                </div>

                {/* IC Search Section */}
                <div className="bg-white rounded-xl border border-slate-200 p-8 shadow-sm">
                    <h2 className="text-xl font-semibold text-slate-900 mb-4">Carian No. Kad Pengenalan</h2>
                    <div className="relative" ref={searchRef}>
                        <div className="relative">
                            <Search className="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-slate-400" />
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Masukkan No Kad Pengenalan"
                                className="w-full pl-14 pr-4 py-4 text-lg border-2 border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all"
                            />
                            {isSearching && (
                                <div className="absolute right-4 top-1/2 -translate-y-1/2">
                                    <div className="animate-spin h-5 w-5 border-2 border-blue-500 border-t-transparent rounded-full"></div>
                                </div>
                            )}
                        </div>

                        {/* Search Results Dropdown */}
                        {showResults && searchResults.length > 0 && (
                            <div className="absolute z-10 w-full mt-2 bg-white border-2 border-slate-200 rounded-lg shadow-xl max-h-96 overflow-y-auto">
                                {searchResults.map((result, index) => (
                                    <button
                                        key={`${result.type}-${result.id}`}
                                        onClick={() => handleResultClick(result)}
                                        className="w-full px-6 py-4 text-left hover:bg-slate-50 border-b border-slate-100 last:border-b-0 transition-colors"
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1">
                                                <div className="flex items-center space-x-2 mb-1">
                                                    <span className="font-semibold text-slate-900">{result.nama}</span>
                                                    <span className={`px-2 py-0.5 text-xs font-medium rounded ${result.type === 'hasil_culaan'
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : 'bg-sky-100 text-sky-700'
                                                        }`}>
                                                        {result.type === 'hasil_culaan' ? 'Hasil Culaan' : 'Data Pengundi'}
                                                    </span>
                                                </div>
                                                <p className="text-sm text-slate-600">No. IC: {result.no_ic}</p>
                                                <p className="text-sm text-slate-600">Tel: {result.no_tel}</p>
                                                <p className="text-xs text-slate-500 mt-1">{result.kadun}, {result.bandar}</p>
                                            </div>
                                            <div className="ml-4">
                                                {result.can_edit ? (
                                                    <Edit className="h-5 w-5 text-blue-600" />
                                                ) : (
                                                    <Eye className="h-5 w-5 text-slate-400" />
                                                )}
                                            </div>
                                        </div>
                                    </button>
                                ))}
                            </div>
                        )}

                        {showResults && searchResults.length === 0 && searchQuery.length >= 3 && !isSearching && (
                            <div className="absolute z-10 w-full mt-2 bg-white border-2 border-slate-200 rounded-lg shadow-xl p-6 text-center">
                                <p className="text-slate-600">Tiada rekod dijumpai</p>
                            </div>
                        )}
                    </div>
                </div>

                {/* Quick Actions */}
                <div>
                    <h2 className="text-xl font-semibold text-slate-900 mb-6 text-center">Tindakan Pantas</h2>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                        {/* Mula Culaan Button */}
                        <button
                            onClick={() => router.visit(route('reports.hasil-culaan.create'))}
                            className="group relative bg-gradient-to-br from-emerald-500 via-emerald-600 to-emerald-700 hover:from-emerald-600 hover:via-emerald-700 hover:to-emerald-800 text-white rounded-2xl p-12 transition-all duration-300 hover:shadow-2xl hover:shadow-emerald-500/50 hover:-translate-y-2 border-2 border-emerald-400/50"
                        >
                            <div className="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent rounded-2xl"></div>
                            <div className="relative flex flex-col items-center text-center space-y-6">
                                <div className="p-6 bg-white/30 backdrop-blur-sm rounded-2xl shadow-lg group-hover:scale-110 transition-transform duration-300">
                                    <ClipboardList className="h-16 w-16" strokeWidth={2.5} />
                                </div>
                                <div>
                                    <h3 className="text-3xl font-bold mb-3 drop-shadow-lg">Mula Culaan</h3>
                                    <p className="text-lg text-emerald-50 font-medium">Tambah data hasil culaan baru</p>
                                </div>
                                <div className="mt-4 px-6 py-2 bg-white/20 backdrop-blur-sm rounded-full">
                                    <span className="text-sm font-semibold">Klik untuk mula</span>
                                </div>
                            </div>
                        </button>

                        {/* Data Pengundi Button */}
                        <button
                            onClick={() => router.visit(route('reports.data-pengundi.create'))}
                            className="group relative bg-gradient-to-br from-sky-500 via-sky-600 to-sky-700 hover:from-sky-600 hover:via-sky-700 hover:to-sky-800 text-white rounded-2xl p-12 transition-all duration-300 hover:shadow-2xl hover:shadow-sky-500/50 hover:-translate-y-2 border-2 border-sky-400/50"
                        >
                            <div className="absolute inset-0 bg-gradient-to-br from-white/20 to-transparent rounded-2xl"></div>
                            <div className="relative flex flex-col items-center text-center space-y-6">
                                <div className="p-6 bg-white/30 backdrop-blur-sm rounded-2xl shadow-lg group-hover:scale-110 transition-transform duration-300">
                                    <UserCheck className="h-16 w-16" strokeWidth={2.5} />
                                </div>
                                <div>
                                    <h3 className="text-3xl font-bold mb-3 drop-shadow-lg">Data Pengundi</h3>
                                    <p className="text-lg text-sky-50 font-medium">Tambah data pengundi baru</p>
                                </div>
                                <div className="mt-4 px-6 py-2 bg-white/20 backdrop-blur-sm rounded-full">
                                    <span className="text-sm font-semibold">Klik untuk mula</span>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            {/* View-Only Modal */}
            {showModal && selectedRecord && (
                <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
                    <div className="bg-white rounded-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                        <div className="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                            <h3 className="text-xl font-semibold text-slate-900">Maklumat Rekod</h3>
                            <button
                                onClick={() => setShowModal(false)}
                                className="p-2 hover:bg-slate-100 rounded-lg transition-colors"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <div className="p-6 space-y-4">
                            <div className="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-4">
                                <p className="text-sm text-amber-800">
                                    <strong>Nota:</strong> Anda hanya boleh melihat rekod ini. Anda tidak mempunyai kebenaran untuk mengedit.
                                </p>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-sm font-medium text-slate-600">Jenis</label>
                                    <p className="text-slate-900">
                                        {selectedRecord.type === 'hasil_culaan' ? 'Hasil Culaan' : 'Data Pengundi'}
                                    </p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-slate-600">No. IC</label>
                                    <p className="text-slate-900">{selectedRecord.no_ic}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-slate-600">Nama</label>
                                    <p className="text-slate-900">{selectedRecord.nama}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-slate-600">No. Telefon</label>
                                    <p className="text-slate-900">{selectedRecord.no_tel}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-slate-600">Bandar</label>
                                    <p className="text-slate-900">{selectedRecord.bandar}</p>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-slate-600">KADUN</label>
                                    <p className="text-slate-900">{selectedRecord.kadun}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
