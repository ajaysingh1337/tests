import React, { useEffect, useState } from 'react';

const TestComponent = () => {
    const [time, setTime] = useState(new Date().toLocaleTimeString());

    useEffect(() => {
        const timer = setInterval(() => {
            setTime(new Date().toLocaleTimeString());
        }, 1000);
        return () => clearInterval(timer);
    }, []);

    return <div>Current Time: {time}</div>;
};

export default TestComponent;
