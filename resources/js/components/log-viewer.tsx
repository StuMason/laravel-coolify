import { useEffect, useRef } from 'react';
import { Card } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { cn } from '@/lib/utils';

interface LogViewerProps {
    logs: string;
    className?: string;
    autoScroll?: boolean;
    maxHeight?: string;
}

export function LogViewer({
    logs,
    className,
    autoScroll = true,
    maxHeight = '500px',
}: LogViewerProps) {
    const scrollRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (autoScroll && scrollRef.current) {
            scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
        }
    }, [logs, autoScroll]);

    const lines = logs.split('\n').filter(Boolean);

    if (lines.length === 0) {
        return (
            <Card className={cn('p-4', className)}>
                <div className="text-center text-gray-500 dark:text-gray-400">
                    No logs available
                </div>
            </Card>
        );
    }

    return (
        <Card className={cn('overflow-hidden p-0', className)}>
            <ScrollArea
                ref={scrollRef}
                className="bg-gray-900 dark:bg-black"
                style={{ maxHeight }}
            >
                <pre className="p-4 text-sm">
                    <code className="font-mono text-gray-100">
                        {lines.map((line, index) => (
                            <LogLine key={index} line={line} lineNumber={index + 1} />
                        ))}
                    </code>
                </pre>
            </ScrollArea>
        </Card>
    );
}

interface LogLineProps {
    line: string;
    lineNumber: number;
}

function LogLine({ line, lineNumber }: LogLineProps) {
    const isError =
        line.toLowerCase().includes('error') ||
        line.toLowerCase().includes('fatal') ||
        line.toLowerCase().includes('failed');
    const isWarning =
        line.toLowerCase().includes('warn') || line.toLowerCase().includes('warning');
    const isSuccess =
        line.toLowerCase().includes('success') ||
        line.toLowerCase().includes('completed') ||
        line.toLowerCase().includes('done');

    return (
        <div
            className={cn(
                'flex',
                isError && 'text-red-400',
                isWarning && 'text-yellow-400',
                isSuccess && 'text-green-400'
            )}
        >
            <span className="mr-4 select-none text-gray-500">{lineNumber.toString().padStart(4, ' ')}</span>
            <span className="flex-1 whitespace-pre-wrap break-all">{line}</span>
        </div>
    );
}
