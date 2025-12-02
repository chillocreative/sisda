import { Combobox, Transition } from '@headlessui/react';
import { Check, ChevronsUpDown, Loader2 } from 'lucide-react';
import { Fragment, useState, useEffect } from 'react';
import axios from 'axios';

export default function AsyncSearchableSelectWithCallback({
    value,
    onChange,
    onSelect,
    placeholder = "Carian...",
    routeName,
    displayField = 'postcode'
}) {
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState([]);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        const fetchOptions = async () => {
            if (query.length < 3) {
                setOptions([]);
                return;
            }

            setLoading(true);
            try {
                const response = await axios.get(route(routeName), {
                    params: { query }
                });
                setOptions(response.data);
            } catch (error) {
                console.error('Error fetching options:', error);
            } finally {
                setLoading(false);
            }
        };

        const timeoutId = setTimeout(fetchOptions, 300);
        return () => clearTimeout(timeoutId);
    }, [query, routeName]);

    const handleSelect = (selectedOption) => {
        if (typeof selectedOption === 'object' && selectedOption !== null) {
            onChange(selectedOption[displayField]);
            if (onSelect) {
                onSelect(selectedOption);
            }
        } else {
            onChange(selectedOption);
        }
    };

    return (
        <div className="relative">
            <Combobox value={value} onChange={handleSelect}>
                <div className="relative w-full cursor-default overflow-hidden rounded-lg bg-white text-left border border-slate-300 focus-within:ring-2 focus-within:ring-slate-400 focus-within:border-slate-400 sm:text-sm">
                    <Combobox.Input
                        className="w-full border-none py-2 pl-3 pr-10 text-sm leading-5 text-gray-900 focus:ring-0"
                        displayValue={(val) => val}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder={placeholder}
                    />
                    <Combobox.Button className="absolute inset-y-0 right-0 flex items-center pr-2">
                        {loading ? (
                            <Loader2 className="h-5 w-5 text-gray-400 animate-spin" />
                        ) : (
                            <ChevronsUpDown
                                className="h-5 w-5 text-gray-400"
                                aria-hidden="true"
                            />
                        )}
                    </Combobox.Button>
                </div>
                <Transition
                    as={Fragment}
                    leave="transition ease-in duration-100"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                    afterLeave={() => setQuery('')}
                >
                    <Combobox.Options className="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                        {options.length === 0 && query !== '' && !loading ? (
                            <div className="relative cursor-default select-none py-2 px-4 text-gray-700">
                                {query.length < 3 ? 'Sila masukkan sekurang-kurangnya 3 digit.' : 'Tiada rekod dijumpai.'}
                            </div>
                        ) : (
                            options.map((option, index) => (
                                <Combobox.Option
                                    key={index}
                                    className={({ active }) =>
                                        `relative cursor-default select-none py-2 pl-10 pr-4 ${active ? 'bg-slate-100 text-slate-900' : 'text-gray-900'
                                        }`
                                    }
                                    value={option}
                                >
                                    {({ selected, active }) => (
                                        <>
                                            <span
                                                className={`block truncate ${selected ? 'font-medium' : 'font-normal'
                                                    }`}
                                            >
                                                {option.postcode} - {option.city}, {option.state}
                                            </span>
                                            {selected ? (
                                                <span
                                                    className={`absolute inset-y-0 left-0 flex items-center pl-3 ${active ? 'text-slate-600' : 'text-slate-600'
                                                        }`}
                                                >
                                                    <Check className="h-5 w-5" aria-hidden="true" />
                                                </span>
                                            ) : null}
                                        </>
                                    )}
                                </Combobox.Option>
                            ))
                        )}
                    </Combobox.Options>
                </Transition>
            </Combobox>
        </div>
    );
}
